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
  text.to_s.downcase.gsub(/[^a-z0-9\s-]/, '').strip.gsub(/\s+/, '-').gsub(/-+/, '-')
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
  YAML.safe_load(File.read(CONFIG_PATH), permitted_classes: [], aliases: false) || {}
end

def ai_enabled?(options)
  options[:use_ai] || options[:openai_api_key] || ENV['OPENAI_API_KEY']
end

def openai_api_key(options)
  options[:openai_api_key] || ENV['OPENAI_API_KEY']
end

def openai_model(options)
  options[:openai_model] || ENV['OPENAI_MODEL'] || 'gpt-4.1-mini'
end

def compact_text(value)
  value.to_s.gsub(/\s+/, ' ').strip
end

def extract_json_payload(text)
  value = text.to_s.strip
  value = value.sub(/\A```json\s*/i, '').sub(/\A```\s*/i, '').sub(/\s*```\z/, '')
  JSON.parse(value)
end

def fetch_json(url)
  uri = URI(url)
  response = Net::HTTP.get_response(uri)
  raise "Request failed for #{uri}: #{response.code} #{response.message}" unless response.is_a?(Net::HTTPSuccess)

  JSON.parse(response.body)
end

def openai_chat_completion(messages, options)
  api_key = openai_api_key(options)
  raise 'OPENAI_API_KEY or --openai-api-key is required when --use-ai is enabled.' unless api_key

  uri = URI('https://api.openai.com/v1/chat/completions')
  request = Net::HTTP::Post.new(uri)
  request['Authorization'] = "Bearer #{api_key}"
  request['Content-Type'] = 'application/json'
  request.body = JSON.generate(
    model: openai_model(options),
    messages: messages,
    response_format: { type: 'json_object' }
  )

  response = Net::HTTP.start(uri.hostname, uri.port, use_ssl: true) { |http| http.request(request) }
  raise "OpenAI API request failed: #{response.code} #{response.message} #{response.body}" unless response.is_a?(Net::HTTPSuccess)

  payload = JSON.parse(response.body)
  content = payload.dig('choices', 0, 'message', 'content')
  raise 'OpenAI API response did not include generated content.' if content.to_s.strip.empty?

  extract_json_payload(content)
end

def ai_post_prompt(kind:, site_config:, input:)
  {
    role: 'system',
    content: <<~PROMPT
      You are writing a single original blog post for #{site_config['title']} by #{site_config['author_fullname'] || site_config['author']}.
      Return valid JSON only with these keys:
      title, description, seo_title, categories, tags, body.
      Requirements:
      - body must be Markdown
      - make the article feel human, useful, and specific
      - avoid generic filler, repetition, and empty hype
      - write for SEO without keyword stuffing
      - categories must be an array of 2 to 4 short category names
      - tags must be an array of 4 to 10 short tags
      - description should be around 140 to 160 characters when practical
      - preserve factual constraints provided in the input
      - do not invent unavailable links or unsupported claims
      - for YouTube pair posts, mention the lyrics and karaoke versions naturally if available
      - for news posts, keep the angle practical and original instead of paraphrased filler
      Context type: #{kind}
      Input:
      #{JSON.pretty_generate(input)}
    PROMPT
  }
end

def ai_generate_post(kind:, site_config:, input:, options:)
  openai_chat_completion(
    [
      ai_post_prompt(kind: kind, site_config: site_config, input: input),
      { role: 'user', content: 'Generate the article JSON now.' }
    ],
    options
  )
end

def resolve_upload_playlist(api_key, channel_id)
  response = fetch_json("https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=#{URI.encode_www_form_component(channel_id)}&key=#{URI.encode_www_form_component(api_key)}")
  response.fetch('items', []).dig(0, 'contentDetails', 'relatedPlaylists', 'uploads') || raise('Could not resolve uploads playlist for the provided YouTube channel.')
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
    {
      'title' => snippet['title'],
      'description' => snippet['description'],
      'publishedAt' => snippet['publishedAt'],
      'videoId' => snippet.dig('resourceId', 'videoId'),
      'url' => "https://www.youtube.com/watch?v=#{snippet.dig('resourceId', 'videoId')}"
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
  playlist_id ||= resolve_upload_playlist(api_key, options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID']) if options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID']
  raise 'Provide --playlist-id or --channel-id when fetching directly from YouTube.' unless playlist_id

  fetch_playlist_items(api_key: api_key, playlist_id: playlist_id)
end

def youtube_description(title, versions)
  labels = versions.map { |item| classify_video_type(item['title']) }.uniq
  if labels.sort == %w[karaoke lyrics]
    "Listen to #{title}, then jump between the lyrics and karaoke versions in one organized post."
  elsif labels.include?('lyrics')
    "A quick guide to the lyrics version of #{title}, with context for listeners and viewers."
  elsif labels.include?('karaoke')
    "A sing-along focused feature for #{title}, built around the available karaoke upload."
  else
    "A structured blog post for #{title}, based on the available YouTube upload."
  end
end

def youtube_categories(versions)
  labels = versions.map { |item| classify_video_type(item['title']) }
  categories = ['music', 'youtube']
  categories << 'lyrics' if labels.include?('lyrics')
  categories << 'karaoke' if labels.include?('karaoke')
  categories.uniq
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
      when Array then "[#{value.map { |item| item.to_s.inspect }.join(', ')}]"
      when TrueClass, FalseClass then value.to_s
      else value.to_s.inspect
      end
    lines << "#{key}: #{formatted}"
  end
  lines << '---'
  lines.join("\n")
end

def build_youtube_post(group_title, versions, site_config)
  sorted = versions.sort_by { |item| item['publishedAt'].to_s }
  date = Date.parse(sorted.first.fetch('publishedAt')).strftime('%Y-%m-%d')
  lyrics = sorted.find { |item| classify_video_type(item['title']) == 'lyrics' }
  karaoke = sorted.find { |item| classify_video_type(item['title']) == 'karaoke' }
  primary = lyrics || karaoke || sorted.first
  description = youtube_description(group_title, sorted)

  body = []
  body << "This post collects the available #{group_title} upload#{sorted.size > 1 ? 's' : ''} from the METAXENOPY channel into one cleaner reading and listening page."
  body << ''
  body << '## What This Post Covers'
  body << ''
  body << '- Basic context around the track or performance'
  body << '- Quick links to the available video versions'
  body << '- A short explanation of why the upload may be useful for listeners or sing-along viewers'
  body << ''
  body << '## Available Video Versions'
  body << ''
  body << "- Watch the lyrics version here: #{lyrics ? "[#{lyrics['title']}](#{lyrics['url']})" : 'No lyrics version is currently linked in this draft.'}"
  body << "- Sing along with the karaoke version here: #{karaoke ? "[#{karaoke['title']}](#{karaoke['url']})" : 'No karaoke version is currently linked in this draft.'}"
  body << ''
  body << '## Why It May Be Worth Visiting'
  body << ''
  body << 'The goal of this post is not to restate the video title, but to give visitors a single, readable page that explains what is available and where to go next. That helps keep the blog more organized and more useful than publishing separate thin pages for nearly identical uploads.'
  body << ''
  body << '## Notes for Readers'
  body << ''
  body << '- This post is connected to the METAXENOPY YouTube channel.'
  body << '- If more versions are published later, this post can be expanded instead of creating duplicate coverage.'
  body << "- For requests or corrections, use the [contact page](#{site_config['url']}#{site_config['baseurl']}/contact/)."
  body << ''
  body << '## About the Publisher'
  body << ''
  body << "#{site_config['title']} is maintained by #{site_config['author_fullname'] || site_config['author']}, combining creator updates with readable music-related posts and practical site pages."

  meta = {
    'layout' => 'post',
    'title' => group_title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => youtube_categories(sorted),
    'tags' => youtube_tags(group_title, sorted),
    'youtube_id' => primary['videoId'],
    'description' => description,
    'seo_title' => "#{group_title} | #{site_config['title']}",
    'published' => false,
    'status' => 'draft'
  }

  [date, build_front_matter(meta), body.join("\n")]
end

def build_ai_youtube_post(group_title, versions, site_config, options)
  sorted = versions.sort_by { |item| item['publishedAt'].to_s }
  date = Date.parse(sorted.first.fetch('publishedAt')).strftime('%Y-%m-%d')
  lyrics = sorted.find { |item| classify_video_type(item['title']) == 'lyrics' }
  karaoke = sorted.find { |item| classify_video_type(item['title']) == 'karaoke' }
  primary = lyrics || karaoke || sorted.first

  ai = ai_generate_post(
    kind: 'youtube',
    site_config: site_config,
    input: {
      required_title: group_title,
      site_description: site_config['description'],
      publisher: site_config['author_fullname'] || site_config['author'],
      versions: sorted.map do |item|
        {
          title: item['title'],
          type: classify_video_type(item['title']),
          published_at: item['publishedAt'],
          video_url: item['url'],
          description: compact_text(item['description'])[0, 400]
        }
      end
    },
    options: options
  )

  meta = {
    'layout' => 'post',
    'title' => compact_text(ai['title']).empty? ? group_title : compact_text(ai['title']),
    'date' => "#{date} 12:00:00 +0800",
    'categories' => Array(ai['categories']).map { |value| compact_text(value) }.reject(&:empty?).first(4),
    'tags' => Array(ai['tags']).map { |value| compact_text(value) }.reject(&:empty?).first(10),
    'youtube_id' => primary['videoId'],
    'description' => compact_text(ai['description']),
    'seo_title' => compact_text(ai['seo_title']).empty? ? "#{group_title} | #{site_config['title']}" : compact_text(ai['seo_title']),
    'published' => false,
    'status' => 'draft'
  }

  [date, build_front_matter(meta), ai['body'].to_s.strip]
end

def write_post_file(date, title, front_matter, body, dry_run: false)
  filename = "#{date}-#{slugify(title)}.md"
  path = POSTS_DIR.join(filename)
  content = "#{front_matter}\n\n#{body.rstrip}\n"
  if dry_run
    puts "Would write #{path}"
  else
    File.write(path, content)
    puts "Created #{path}"
  end
end

def run_youtube(options)
  site_config = load_site_config
  existing = existing_post_slugs
  videos = load_video_items(options)
  groups = videos.group_by { |item| normalize_video_title(item['title']) }

  groups.each do |group_title, versions|
    next if group_title.to_s.strip.empty?

    slug = slugify(group_title)
    if existing.include?(slug)
      puts "Skipping #{group_title}: matching post slug already exists."
      next
    end

    date, front_matter, body = if ai_enabled?(options)
      build_ai_youtube_post(group_title, versions, site_config, options)
    else
      build_youtube_post(group_title, versions, site_config)
    end
    write_post_file(date, group_title, front_matter, body, dry_run: options[:dry_run])
  end
end

def build_news_post(options, site_config)
  topic = options.fetch(:topic)
  source_name = options[:source_name] || 'Primary source'
  source_url = options[:source_url] || ''
  angle = options[:angle] || 'Explain the update clearly, focus on practical meaning, and avoid recycled filler.'
  date = options[:date] || Date.today.strftime('%Y-%m-%d')
  title = options[:title] || topic
  categories = (options[:categories] || 'news, tech').split(',').map(&:strip).reject(&:empty?)
  tags = (options[:tags] || slugify(topic).split('-').join(',')).split(',').map(&:strip).reject(&:empty?).first(10)

  body = <<~MARKDOWN
    This draft is meant to turn the topic into a more useful article instead of a shallow rewrite. Before publishing, replace placeholders with verified details, original framing, and any important context from the primary source.

    ## What Happened

    Explain the core update in plain language. Summarize the verified announcement, release, advisory, or event without padding the post with generic filler.

    ## Why It Matters

    Describe who is affected, what changed, and why the update is worth a reader's attention.

    ## Practical Takeaways

    - Clarify the most important action or takeaway.
    - Mention risks, limitations, or caveats.
    - Point readers to the most relevant next step.

    ## Source Context

    - Source: #{source_name}#{source_url.empty? ? '' : " ([link](#{source_url}))"}
    - Editorial angle: #{angle}
    - Publisher: #{site_config['author_fullname'] || site_config['author']}

    ## Before Publishing

    - Confirm the factual details against the source.
    - Add an original explanation or takeaway.
    - Add relevant internal links if the topic connects to an existing post or page.
  MARKDOWN

  meta = {
    'layout' => 'post',
    'title' => title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => categories,
    'tags' => tags,
    'description' => "Draft article on #{topic}, prepared for a clearer, higher-value published post.",
    'seo_title' => "#{title} | #{site_config['title']}",
    'published' => false,
    'status' => 'draft'
  }

  [date, title, build_front_matter(meta), body]
end

def build_ai_news_post(options, site_config)
  topic = options.fetch(:topic)
  source_name = options[:source_name] || 'Primary source'
  source_url = options[:source_url] || ''
  angle = options[:angle] || 'Explain the update clearly, focus on practical meaning, and avoid recycled filler.'
  date = options[:date] || Date.today.strftime('%Y-%m-%d')

  ai = ai_generate_post(
    kind: 'news',
    site_config: site_config,
    input: {
      required_topic: topic,
      suggested_title: options[:title],
      source_name: source_name,
      source_url: source_url,
      editorial_angle: angle,
      preferred_categories: options[:categories],
      preferred_tags: options[:tags],
      publisher: site_config['author_fullname'] || site_config['author'],
      site_description: site_config['description']
    },
    options: options
  )

  title = compact_text(ai['title']).empty? ? (options[:title] || topic) : compact_text(ai['title'])
  meta = {
    'layout' => 'post',
    'title' => title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => Array(ai['categories']).map { |value| compact_text(value) }.reject(&:empty?).first(4),
    'tags' => Array(ai['tags']).map { |value| compact_text(value) }.reject(&:empty?).first(10),
    'description' => compact_text(ai['description']),
    'seo_title' => compact_text(ai['seo_title']).empty? ? "#{title} | #{site_config['title']}" : compact_text(ai['seo_title']),
    'published' => false,
    'status' => 'draft'
  }

  [date, title, build_front_matter(meta), ai['body'].to_s.strip]
end

def run_news(options)
  site_config = load_site_config
  date, title, front_matter, body = if ai_enabled?(options)
    build_ai_news_post(options, site_config)
  else
    build_news_post(options, site_config)
  end
  write_post_file(date, title, front_matter, body, dry_run: options[:dry_run])
end

def parse_command(argv)
  command = argv.shift
  case command
  when 'youtube'
    options = { dry_run: false }
    OptionParser.new do |opts|
      opts.banner = 'Usage: ruby scripts/publish_content.rb youtube [options]'
      opts.on('--input FILE', 'Load YouTube items from a local JSON file') { |value| options[:input] = value }
      opts.on('--api-key KEY', 'YouTube Data API key') { |value| options[:api_key] = value }
      opts.on('--channel-id ID', 'YouTube channel ID') { |value| options[:channel_id] = value }
      opts.on('--playlist-id ID', 'Uploads playlist ID') { |value| options[:playlist_id] = value }
      opts.on('--use-ai', 'Use OpenAI to generate more unique SEO-friendly post content') { options[:use_ai] = true }
      opts.on('--openai-api-key KEY', 'OpenAI API key for AI-assisted generation') { |value| options[:openai_api_key] = value }
      opts.on('--openai-model MODEL', 'OpenAI model name for AI-assisted generation') { |value| options[:openai_model] = value }
      opts.on('--dry-run', 'Preview output without writing files') { options[:dry_run] = true }
    end.parse!(argv)
    [:youtube, options]
  when 'news'
    options = { dry_run: false }
    OptionParser.new do |opts|
      opts.banner = 'Usage: ruby scripts/publish_content.rb news [options]'
      opts.on('--topic TEXT', 'Main topic for the draft') { |value| options[:topic] = value }
      opts.on('--title TEXT', 'Override the generated title') { |value| options[:title] = value }
      opts.on('--source-name TEXT', 'Source or publication name') { |value| options[:source_name] = value }
      opts.on('--source-url URL', 'Source URL') { |value| options[:source_url] = value }
      opts.on('--angle TEXT', 'Original framing or editorial angle') { |value| options[:angle] = value }
      opts.on('--date YYYY-MM-DD', 'Draft date') { |value| options[:date] = value }
      opts.on('--categories CSV', 'Comma-separated categories') { |value| options[:categories] = value }
      opts.on('--tags CSV', 'Comma-separated tags') { |value| options[:tags] = value }
      opts.on('--use-ai', 'Use OpenAI to generate more unique SEO-friendly post content') { options[:use_ai] = true }
      opts.on('--openai-api-key KEY', 'OpenAI API key for AI-assisted generation') { |value| options[:openai_api_key] = value }
      opts.on('--openai-model MODEL', 'OpenAI model name for AI-assisted generation') { |value| options[:openai_model] = value }
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
