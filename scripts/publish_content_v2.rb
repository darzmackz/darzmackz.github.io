#!/usr/bin/env ruby
# frozen_string_literal: true

require 'date'
require 'json'
require 'net/http'
require 'optparse'
require 'pathname'
require 'set'
require 'uri'
require 'yaml'

ROOT = Pathname.new(__dir__).join('..').expand_path
POSTS_DIR = ROOT.join('_posts')
CONFIG_PATH = ROOT.join('_config.yml')

def slugify(text)
  text.to_s
      .downcase
      .gsub(/[^a-z0-9\s-]/, '')
      .strip
      .gsub(/\s+/, '-')
      .gsub(/-+/, '-')
end

def compact_text(value)
  value.to_s.gsub(/\s+/, ' ').strip
end

def normalize_video_title(title)
  title.to_s.gsub(/\s*[\(\[]\s*(lyrics|karaoke)\s*[\)\]]\s*$/i, '').strip
end

def classify_video_type(title)
  return 'lyrics' if title.to_s.match?(/\blyrics\b/i)
  return 'karaoke' if title.to_s.match?(/\bkaraoke\b/i)

  'video'
end

def existing_post_slugs
  Dir.glob(POSTS_DIR.join('*.md')).map do |path|
    File.basename(path).sub(/^\d{4}-\d{2}-\d{2}-/, '').sub(/\.md$/, '')
  end.to_set
end

def load_site_config
  return {} unless CONFIG_PATH.exist?

  YAML.safe_load(File.read(CONFIG_PATH), permitted_classes: [], aliases: false) || {}
end

def fetch_json(url)
  uri = URI(url)
  response = Net::HTTP.get_response(uri)
  raise "Request failed for #{uri}: #{response.code} #{response.message}" unless response.is_a?(Net::HTTPSuccess)

  JSON.parse(response.body)
end

def resolve_upload_playlist(api_key, channel_id)
  response = fetch_json(
    "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=#{URI.encode_www_form_component(channel_id)}&key=#{URI.encode_www_form_component(api_key)}"
  )

  response.fetch('items', []).dig(0, 'contentDetails', 'relatedPlaylists', 'uploads') ||
    raise('Could not resolve uploads playlist for the provided YouTube channel.')
end

def fetch_playlist_items(api_key:, playlist_id:)
  items = []
  page_token = nil

  loop do
    url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=#{URI.encode_www_form_component(playlist_id)}&key=#{URI.encode_www_form_component(api_key)}"
    url += "&pageToken=#{URI.encode_www_form_component(page_token)}" if page_token

    response = fetch_json(url)
    items.concat(response.fetch('items', []))
    page_token = response['nextPageToken']
    break unless page_token
  end

  items.map do |item|
    snippet = item.fetch('snippet', {})
    video_id = snippet.dig('resourceId', 'videoId')

    {
      'title' => snippet['title'],
      'description' => snippet['description'],
      'publishedAt' => snippet['publishedAt'],
      'videoId' => video_id,
      'url' => "https://www.youtube.com/watch?v=#{video_id}"
    }
  end
end

def load_video_items(options)
  if options[:input]
    parsed = JSON.parse(File.read(options[:input]))
    source_items = parsed.is_a?(Hash) ? parsed.fetch('items', []) : parsed

    return source_items.map do |item|
      snippet = item.fetch('snippet', item)
      video_id = snippet['videoId'] || snippet.dig('resourceId', 'videoId')

      {
        'title' => snippet['title'],
        'description' => snippet['description'],
        'publishedAt' => snippet['publishedAt'],
        'videoId' => video_id,
        'url' => snippet['url'] || "https://www.youtube.com/watch?v=#{video_id}"
      }
    end
  end

  api_key = options[:api_key] || ENV['YOUTUBE_API_KEY']
  raise 'Provide --input or set --api-key / YOUTUBE_API_KEY.' unless api_key

  playlist_id = options[:playlist_id]
  if playlist_id.nil? && (options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID'])
    playlist_id = resolve_upload_playlist(api_key, options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID'])
  end

  raise 'Provide --playlist-id or --channel-id when fetching directly from YouTube.' unless playlist_id

  fetch_playlist_items(api_key: api_key, playlist_id: playlist_id)
end

def youtube_description(title, versions)
  labels = versions.map { |item| classify_video_type(item['title']) }.uniq.sort

  if labels == %w[karaoke lyrics]
    "A draft post for #{title} that combines both lyrics and karaoke versions into one cleaner, more useful blog entry."
  elsif labels.include?('lyrics')
    "A draft post for #{title} focused on the available lyrics version with a cleaner reading and listening experience."
  elsif labels.include?('karaoke')
    "A draft post for #{title} focused on the karaoke version for sing-along readers and viewers."
  else
    "A structured draft post for #{title} based on the available YouTube upload."
  end
end

def youtube_categories(versions)
  labels = versions.map { |item| classify_video_type(item['title']) }

  categories = ['music', 'youtube']
  categories << 'lyrics' if labels.include?('lyrics')
  categories << 'karaoke' if labels.include?('karaoke')
  categories.uniq.first(4)
end

def youtube_tags(title, versions)
  base = slugify(title).split('-').reject { |part| part.length < 3 }
  labels = versions.map { |item| classify_video_type(item['title']) }

  (base + labels + ['metaxenopy']).uniq.first(10)
end

def build_front_matter(meta)
  lines = ['---']

  meta.each do |key, value|
    next if value.nil? || value == ''

    formatted =
      case value
      when Array
        "[#{value.map { |item| item.to_s.inspect }.join(', ')}]"
      when TrueClass, FalseClass
        value.to_s
      else
        value.to_s.inspect
      end

    lines << "#{key}: #{formatted}"
  end

  lines << '---'
  lines.join("\n")
end

def build_youtube_prompt(group_title, versions, site_config)
  sorted = versions.sort_by { |item| item['publishedAt'].to_s }
  lyrics = sorted.find { |item| classify_video_type(item['title']) == 'lyrics' }
  karaoke = sorted.find { |item| classify_video_type(item['title']) == 'karaoke' }
  video_types = sorted.map { |item| classify_video_type(item['title']) }.uniq
  site_title = compact_text(site_config['title'])
  site_author = compact_text(site_config['author_fullname'] || site_config['author'])
  publish_date = Date.parse(sorted.first.fetch('publishedAt')).strftime('%Y-%m-%d')

  lines = []
  lines << 'Create a unique blog post in Markdown.'
  lines << ''
  lines << 'Requirements:'
  lines << "- Keep the title exactly: \"#{group_title}\""
  lines << "- Use the blog post date exactly: #{publish_date}"
  lines << '- Make it SEO-friendly but natural'
  lines << '- Make it human, readable, and not robotic'
  lines << '- Use the title as the main SEO topic naturally'
  lines << '- Do not invent facts beyond the provided details'
  lines << '- Write an engaging intro, not a generic one'
  lines << '- Use clean section headings'
  lines << '- Add a short natural closing paragraph'
  lines << '- Make the article feel handcrafted and useful to readers'
  lines << '- Avoid generic filler and repetitive phrasing'
  lines << '- Do not mention anything about AI, automation, or draft generation'
  if lyrics && karaoke
    lines << '- Since both lyrics and karaoke versions exist, combine them naturally into one post'
    lines << '- Mention the lyrics version as the one for readers who want to follow the words'
    lines << '- Mention the karaoke version as the one for readers who want to sing along'
  elsif lyrics
    lines << '- Focus naturally on the lyrics version'
  elsif karaoke
    lines << '- Focus naturally on the karaoke version'
  else
    lines << '- Focus naturally on the single available video'
  end
  lines << ''
  lines << 'Available source data:'
  lines << "- Final post title: #{group_title}"
  lines << "- Channel: METAXENOPY"
  lines << "- Site title: #{site_title}" unless site_title.empty?
  lines << "- Site author: #{site_author}" unless site_author.empty?
  lines << "- YouTube publish date: #{publish_date}"
  lines << "- Available video types: #{video_types.join(', ')}"
  lines << ''
  lines << 'Video sources:'
  sorted.each do |item|
    lines << "- #{item['title']} => #{item['url']}"
  end
  unless compact_text(sorted.first['description']).empty?
    lines << ''
    lines << 'Source description snippets:'
    sorted.each do |item|
      snippet = compact_text(item['description'])[0, 400]
      next if snippet.empty?

      lines << "- #{item['title']}: #{snippet}"
    end
  end
  lines << ''
  lines << 'Return the final answer in this exact order:'
  lines << '1. SEO Title'
  lines << '2. Excerpt/Description'
  lines << '3. Categories'
  lines << '4. Tags'
  lines << '5. Markdown Content'
  lines << ''
  lines << 'Formatting rules for your answer:'
  lines << '- SEO Title: one line only'
  lines << '- Excerpt/Description: one paragraph only'
  lines << '- Categories: comma-separated list'
  lines << '- Tags: comma-separated list'
  lines << '- Markdown Content: full blog post only'
  lines.join("\n")
end

def build_news_prompt(options, site_config)
  topic = compact_text(options.fetch(:topic))
  title = compact_text(options[:title] || topic)
  source_name = compact_text(options[:source_name] || 'Primary source')
  source_url = compact_text(options[:source_url])
  summary = compact_text(options[:summary])
  key_points = compact_text(options[:key_points]).split('|').map(&:strip).reject(&:empty?)
  date = compact_text(options[:date] || Date.today.strftime('%Y-%m-%d'))
  site_title = compact_text(site_config['title'])
  site_author = compact_text(site_config['author_fullname'] || site_config['author'])

  lines = []
  lines << 'Write a unique IT or news blog post in Markdown.'
  lines << ''
  lines << 'Requirements:'
  lines << "- Keep the main topic exactly: #{topic}"
  lines << "- Use the blog post date exactly: #{date}"
  lines << '- Keep it factual and useful'
  lines << '- Do not invent claims not supported by the source data'
  lines << '- Make it understandable for normal readers'
  lines << '- Focus on what happened, why it matters, and what readers should pay attention to'
  lines << '- Avoid generic filler and robotic phrasing'
  lines << '- Keep it SEO-friendly but natural'
  lines << '- Do not mention anything about AI, automation, or draft generation'
  lines << ''
  lines << 'Available source data:'
  lines << "- Main topic: #{topic}"
  lines << "- Suggested title: #{title}"
  lines << "- Source name: #{source_name}"
  lines << "- Source URL: #{source_url}" unless source_url.empty?
  lines << "- Site title: #{site_title}" unless site_title.empty?
  lines << "- Site author: #{site_author}" unless site_author.empty?
  lines << "- Verified summary: #{summary}" unless summary.empty?
  unless key_points.empty?
    lines << '- Key points:'
    key_points.each { |point| lines << "  - #{point}" }
  end
  lines << ''
  lines << 'Return the final answer in this exact order:'
  lines << '1. SEO Title'
  lines << '2. Excerpt/Description'
  lines << '3. Categories'
  lines << '4. Tags'
  lines << '5. Markdown Content'
  lines << ''
  lines << 'Formatting rules for your answer:'
  lines << '- SEO Title: one line only'
  lines << '- Excerpt/Description: one paragraph only'
  lines << '- Categories: comma-separated list'
  lines << '- Tags: comma-separated list'
  lines << '- Markdown Content: full blog post only'
  lines.join("\n")
end

def build_youtube_post(group_title, versions, site_config)
  sorted = versions.sort_by { |item| item['publishedAt'].to_s }
  date = Date.parse(sorted.first.fetch('publishedAt')).strftime('%Y-%m-%d')
  lyrics = sorted.find { |item| classify_video_type(item['title']) == 'lyrics' }
  karaoke = sorted.find { |item| classify_video_type(item['title']) == 'karaoke' }
  primary = lyrics || karaoke || sorted.first
  prompt_text = build_youtube_prompt(group_title, sorted, site_config)

  meta = {
    'layout' => 'post',
    'title' => group_title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => youtube_categories(sorted),
    'tags' => youtube_tags(group_title, sorted),
    'youtube_id' => primary['videoId'],
    'description' => youtube_description(group_title, sorted),
    'seo_title' => "#{group_title} | #{site_config['title'] || 'Site'}",
    'published' => false,
    'status' => 'draft'
  }

  body = []
  body << '## SOURCE_DATA'
  body << ''
  body << "- Final post title: #{group_title}"
  body << "- Channel: METAXENOPY"
  body << "- Blog post date: #{date}"
  body << "- Available video types: #{sorted.map { |item| classify_video_type(item['title']) }.uniq.join(', ')}"
  body << ''
  body << '### Video Links'
  body << ''
  sorted.each do |item|
    body << "- #{item['title']}: #{item['url']}"
  end
  body << ''
  unless compact_text(primary['description']).empty?
    body << '### Source Description Snippets'
    body << ''
    sorted.each do |item|
      snippet = compact_text(item['description'])[0, 400]
      next if snippet.empty?

      body << "- #{item['title']}: #{snippet}"
    end
    body << ''
  end
  body << '## AI_DRAFT_PROMPT'
  body << ''
  body << '```text'
  body << (prompt_text.nil? || prompt_text.strip.empty? ? 'ERROR: Prompt generation failed.' : prompt_text)
  body << '```'
  body << ''
  body << '## CONTENT'
  body << ''
  body << 'Paste the AI_DRAFT_PROMPT into ChatGPT, then replace this section with the final polished Markdown content.'

  [date, build_front_matter(meta), body.join("\n")]
end

def build_news_post(options, site_config)
  topic = options.fetch(:topic)
  source_name = options[:source_name] || 'Primary source'
  source_url = options[:source_url] || ''
  summary = options[:summary] || ''
  key_points = (options[:key_points] || '').split('|').map(&:strip).reject(&:empty?)
  date = options[:date] || Date.today.strftime('%Y-%m-%d')
  title = options[:title] || topic
  categories = (options[:categories] || 'news, tech').split(',').map(&:strip).reject(&:empty?)
  tags = (options[:tags] || slugify(topic).split('-').join(',')).split(',').map(&:strip).reject(&:empty?).first(10)
  prompt_text = build_news_prompt(options, site_config)

  meta = {
    'layout' => 'post',
    'title' => title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => categories,
    'tags' => tags,
    'description' => "Draft article on #{topic}, prepared for a clearer and more useful published post.",
    'seo_title' => "#{title} | #{site_config['title'] || 'Site'}",
    'published' => false,
    'status' => 'draft'
  }

  body = []
  body << '## SOURCE_DATA'
  body << ''
  body << "- Main topic: #{topic}"
  body << "- Suggested title: #{title}"
  body << "- Source name: #{source_name}"
  body << "- Source URL: #{source_url}" unless compact_text(source_url).empty?
  body << "- Date: #{date}"
  body << "- Verified summary: #{summary}" unless compact_text(summary).empty?
  unless key_points.empty?
    body << ''
    body << '### Key Points'
    body << ''
    key_points.each { |point| body << "- #{point}" }
  end
  body << ''
  body << '## AI_DRAFT_PROMPT'
  body << ''
  body << '```text'
  body << (prompt_text.nil? || prompt_text.strip.empty? ? 'ERROR: Prompt generation failed.' : prompt_text)
  body << '```'
  body << ''
  body << '## CONTENT'
  body << ''
  body << 'Paste the AI_DRAFT_PROMPT into ChatGPT, then replace this section with the final polished Markdown content.'

  [date, title, build_front_matter(meta), body.join("\n")]
end

def write_post_file(date, title, front_matter, body, dry_run: false, overwrite: false)
  filename = "#{date}-#{slugify(title)}.md"
  path = POSTS_DIR.join(filename)
  content = "#{front_matter}\n\n#{body.rstrip}\n"

  if dry_run
    puts "Would write #{path}"
    return
  end

  if path.exist? && !overwrite
    puts "Skipping #{title}: file already exists at #{path}."
    return
  end

  File.write(path, content)
  puts "#{path.exist? ? 'Created' : 'Created'} #{path}"
end

def run_youtube(options)
  site_config = load_site_config
  existing = existing_post_slugs
  videos = load_video_items(options)
  groups = videos.group_by { |item| normalize_video_title(item['title']) }

  groups.each do |group_title, versions|
    next if group_title.to_s.strip.empty?

    slug = slugify(group_title)
    if existing.include?(slug) && !options[:force]
      puts "Skipping #{group_title}: matching post slug already exists. Use --force to overwrite."
      next
    end

    puts "Generating prompt-ready draft for: #{group_title}"
    date, front_matter, body = build_youtube_post(group_title, versions, site_config)
    write_post_file(date, group_title, front_matter, body, dry_run: options[:dry_run], overwrite: options[:force])
  end
end

def run_news(options)
  site_config = load_site_config
  date, title, front_matter, body = build_news_post(options, site_config)
  write_post_file(date, title, front_matter, body, dry_run: options[:dry_run], overwrite: options[:force])
end

def parse_command(argv)
  command = argv.shift

  case command
  when 'youtube'
    options = { dry_run: false, force: false }
    OptionParser.new do |opts|
      opts.banner = 'Usage: ruby scripts/publish_content_v2.rb youtube [options]'
      opts.on('--input FILE', 'Load YouTube items from a local JSON file') { |value| options[:input] = value }
      opts.on('--api-key KEY', 'YouTube Data API key') { |value| options[:api_key] = value }
      opts.on('--channel-id ID', 'YouTube channel ID') { |value| options[:channel_id] = value }
      opts.on('--playlist-id ID', 'Uploads playlist ID') { |value| options[:playlist_id] = value }
      opts.on('--force', 'Overwrite existing matching draft file') { options[:force] = true }
      opts.on('--dry-run', 'Preview output without writing files') { options[:dry_run] = true }
    end.parse!(argv)

    [:youtube, options]
  when 'news'
    options = { dry_run: false, force: false }
    OptionParser.new do |opts|
      opts.banner = 'Usage: ruby scripts/publish_content_v2.rb news [options]'
      opts.on('--topic TEXT', 'Main topic for the draft') { |value| options[:topic] = value }
      opts.on('--title TEXT', 'Override the post title') { |value| options[:title] = value }
      opts.on('--source-name TEXT', 'Source or publication name') { |value| options[:source_name] = value }
      opts.on('--source-url URL', 'Source URL') { |value| options[:source_url] = value }
      opts.on('--summary TEXT', 'Short verified summary from the source') { |value| options[:summary] = value }
      opts.on('--key-points TEXT', 'Pipe-separated key points, e.g. "point1|point2|point3"') { |value| options[:key_points] = value }
      opts.on('--date YYYY-MM-DD', 'Draft date') { |value| options[:date] = value }
      opts.on('--categories CSV', 'Comma-separated categories') { |value| options[:categories] = value }
      opts.on('--tags CSV', 'Comma-separated tags') { |value| options[:tags] = value }
      opts.on('--force', 'Overwrite existing matching draft file') { options[:force] = true }
      opts.on('--dry-run', 'Preview output without writing files') { options[:dry_run] = true }
    end.parse!(argv)

    raise OptionParser::MissingArgument, '--topic is required' unless options[:topic]

    [:news, options]
  else
    raise OptionParser::InvalidArgument, 'Use either "youtube" or "news".'
  end
end

command, options = parse_command(ARGV)
command == :youtube ? run_youtube(options) : run_news(options)