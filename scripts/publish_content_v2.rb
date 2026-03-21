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
require 'digest'

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

def safe_excerpt(text, max_length = 160)
  value = compact_text(text)
  return value if value.length <= max_length

  shortened = value[0, max_length - 3]
  shortened = shortened.sub(/\s+\S*\z/, '')
  "#{shortened}..."
end

def normalize_video_title(title)
  title.to_s.gsub(/\s*[\(\[]\s*(lyrics|karaoke)\s*[\)\]]\s*$/i, '').strip
end

def classify_video_type(title)
  return 'lyrics' if title.to_s.match?(/\blyrics\b/i)
  return 'karaoke' if title.to_s.match?(/\bkaraoke\b/i)

  'video'
end

def title_keywords(title)
  stop_words = %w[
    the a an and or but with without for from into onto over under on in at by to of is are
    official audio video live version studio world tour cover remix edit lyrics karaoke full hd
  ]

  slugify(title)
    .split('-')
    .map(&:strip)
    .reject { |word| word.empty? || word.length < 3 || stop_words.include?(word) }
    .uniq
end

def parse_artist_and_track(title)
  cleaned = normalize_video_title(title)
  return [nil, cleaned] unless cleaned.include?(' - ')

  artist, track = cleaned.split(/\s+-\s+/, 2)
  [compact_text(artist), compact_text(track)]
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
    response_format: { type: 'json_object' },
    temperature: 0.9
  )

  response = Net::HTTP.start(uri.hostname, uri.port, use_ssl: true) { |http| http.request(request) }
  unless response.is_a?(Net::HTTPSuccess)
    raise "OpenAI API request failed: #{response.code} #{response.message} #{response.body}"
  end

  payload = JSON.parse(response.body)
  content = payload.dig('choices', 0, 'message', 'content')
  raise 'OpenAI API response did not include generated content.' if content.to_s.strip.empty?

  extract_json_payload(content)
end

def ai_post_prompt(kind:, site_config:, input:)
  publisher_name = site_config['author_fullname'] || site_config['author'] || site_config['title']
  site_title = site_config['title'] || 'the site'
  seed = input[:uniqueness_seed] || input['uniqueness_seed'] || Digest::SHA256.hexdigest(JSON.generate(input))[0, 12]

  {
    role: 'system',
    content: <<~PROMPT
      You are an expert SEO blog writer and content strategist for #{site_title}.
      The publisher is #{publisher_name}.

      You must create ONE original, human-like, reader-friendly blog post.
      The writing must feel handcrafted, not templated.

      OUTPUT FORMAT:
      Return valid JSON ONLY with exactly these keys:
      title, description, seo_title, categories, tags, body

      HARD REQUIREMENTS:
      - body MUST be Markdown
      - Every post must feel unique in wording, flow, and emphasis
      - Do not sound robotic, repetitive, generic, or auto-generated
      - Do not use empty hype, filler, or keyword stuffing
      - Do not invent facts, quotes, history, chart positions, or unsupported claims
      - Only use information supported by the provided input
      - Preserve required factual constraints exactly
      - Use the given title/topic as the main SEO anchor naturally
      - Make the article useful and pleasant to read
      - The intro should hook the reader quickly
      - Use clean natural headings, not stiff boilerplate headings unless they fit
      - Vary sentence lengths for a more human tone
      - Avoid lines like:
        "This article will..."
        "In conclusion..."
        "In today's world..."
        "This blog post explores..."

      SEO REQUIREMENTS:
      - Use the provided required title/topic as the primary keyword target
      - Build a strong seo_title that is clickable and natural
      - description should usually be around 140 to 160 characters
      - categories must be an array of 2 to 4 short category names
      - tags must be an array of 5 to 10 short tags
      - Prefer tags people would realistically search for

      YOUTUBE-SPECIFIC RULES:
      - Base the content strongly on the video title and available version types
      - If the content is music-related, write in a way fans and casual readers would both enjoy
      - If a lyrics version exists, mention the value of following the words, meaning, or mood
      - If a karaoke version exists, mention the sing-along angle naturally
      - If both lyrics and karaoke exist, combine them into one cohesive post without sounding repetitive
      - Do not simply restate the video title over and over
      - Do not create thin content; add genuine reader value through framing, context, usefulness, and clear navigation

      NEWS-SPECIFIC RULES:
      - Keep the angle practical and original
      - Do not rewrite source material in a shallow way
      - Focus on why readers should care

      STYLE TARGET:
      - polished
      - natural
      - clear
      - SEO-aware
      - useful
      - not spammy

      UNIQUENESS SEED:
      #{seed}

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
  playlist_id ||= resolve_upload_playlist(api_key, options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID']) if options[:channel_id] || ENV['YOUTUBE_CHANNEL_ID']
  raise 'Provide --playlist-id or --channel-id when fetching directly from YouTube.' unless playlist_id

  fetch_playlist_items(api_key: api_key, playlist_id: playlist_id)
end

def youtube_description(title, versions)
  labels = versions.map { |item| classify_video_type(item['title']) }.uniq
  if labels.sort == %w[karaoke lyrics]
    "Explore #{title} in one post with both lyrics and karaoke links, plus a cleaner overview for readers and listeners."
  elsif labels.include?('lyrics')
    "Read a cleaner overview of #{title} and jump straight to the available lyrics video in one organized post."
  elsif labels.include?('karaoke')
    "Sing along with #{title} through this karaoke-focused post, built for quick access and a better reader experience."
  else
    "Discover #{title} in a cleaner, SEO-friendly post built around the available YouTube upload."
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
  base = title_keywords(title)
  artist, track = parse_artist_and_track(title)
  labels = versions.map { |item| classify_video_type(item['title']) }

  tags = []
  tags.concat(base)
  tags << slugify(artist).tr('-', ' ') if artist
  tags << slugify(track).tr('-', ' ') if track
  tags.concat(labels)
  tags << 'metaxenopy'
  tags.map { |tag| compact_text(tag) }.reject(&:empty?).uniq.first(10)
end

def sanitize_categories(value, fallback)
  items = Array(value).map { |item| compact_text(item) }.reject(&:empty?).uniq
  items = fallback if items.empty?
  items.first(4)
end

def sanitize_tags(value, fallback)
  items = Array(value).map { |item| compact_text(item) }.reject(&:empty?).uniq
  items = fallback if items.empty?
  items.first(10)
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

def build_youtube_post(group_title, versions, site_config)
  sorted = versions.sort_by { |item| item['publishedAt'].to_s }
  date = Date.parse(sorted.first.fetch('publishedAt')).strftime('%Y-%m-%d')
  lyrics = sorted.find { |item| classify_video_type(item['title']) == 'lyrics' }
  karaoke = sorted.find { |item| classify_video_type(item['title']) == 'karaoke' }
  primary = lyrics || karaoke || sorted.first
  artist, track = parse_artist_and_track(group_title)

  intro_line =
    if lyrics && karaoke
      "If you're looking for a cleaner way to enjoy #{group_title}, this post brings the lyrics and karaoke versions together in one place."
    elsif lyrics
      "If you want a simpler way to follow #{group_title}, this post points straight to the available lyrics version and gives it a more readable home on the site."
    elsif karaoke
      "If you want to sing along with #{group_title}, this post highlights the available karaoke version in a cleaner format for readers and viewers."
    else
      "This post gives #{group_title} a more readable page on the site, built around the available YouTube upload."
    end

  body = []
  body << intro_line
  body << ''
  body << "The goal is to make the page more useful than a bare video title by giving visitors a quick explanation, a cleaner structure, and direct links to the available version#{sorted.size > 1 ? 's' : ''}."
  body << ''
  if artist || track
    body << '## Quick Overview'
    body << ''
    body << "- Artist: #{artist}" if artist
    body << "- Title: #{track}" if track
    body << "- Available formats: #{sorted.map { |item| classify_video_type(item['title']) }.uniq.join(', ')}"
    body << ''
  end
  body << '## Watch or Sing Along'
  body << ''
  body << "- Lyrics version: #{lyrics ? "[#{lyrics['title']}](#{lyrics['url']})" : 'Not available in this draft.'}"
  body << "- Karaoke version: #{karaoke ? "[#{karaoke['title']}](#{karaoke['url']})" : 'Not available in this draft.'}"
  unless lyrics || karaoke
    body << "- Main video: [#{primary['title']}](#{primary['url']})"
  end
  body << ''
  body << '## Why This Post Exists'
  body << ''
  body << 'Instead of creating multiple thin posts for closely related uploads, this page keeps matching versions together. That makes it easier for readers to find what they want and gives the site a more useful, organized structure.'
  body << ''
  body << '## Reader Notes'
  body << ''
  body << "- Source channel: METAXENOPY"
  body << '- This draft can be expanded later if more matching versions are uploaded.'
  if site_config['url']
    baseurl = site_config['baseurl'].to_s
    body << "- For questions or updates, use the [contact page](#{site_config['url']}#{baseurl}/contact/)."
  end

  meta = {
    'layout' => 'post',
    'title' => group_title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => youtube_categories(sorted),
    'tags' => youtube_tags(group_title, sorted),
    'youtube_id' => primary['videoId'],
    'description' => youtube_description(group_title, sorted),
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
  artist, track = parse_artist_and_track(group_title)

  input = {
    required_title: group_title,
    publisher: site_config['author_fullname'] || site_config['author'],
    site_title: site_config['title'],
    site_description: site_config['description'],
    primary_keywords: title_keywords(group_title),
    content_intent: sorted.map { |v| classify_video_type(v['title']) }.uniq,
    artist: artist,
    track: track,
    required_constraints: {
      post_date: date,
      use_youtube_publish_date: true,
      merge_related_lyrics_and_karaoke_posts: true,
      keep_claims_grounded_in_input: true
    },
    uniqueness_seed: Digest::SHA256.hexdigest("#{group_title}-#{sorted.map { |v| v['videoId'] }.join('-')}")[0, 16],
    versions: sorted.map do |item|
      {
        title: item['title'],
        type: classify_video_type(item['title']),
        published_at: item['publishedAt'],
        video_url: item['url'],
        description: compact_text(item['description'])[0, 600]
      }
    end
  }

  ai = ai_generate_post(
    kind: 'youtube',
    site_config: site_config,
    input: input,
    options: options
  )

  fallback_categories = youtube_categories(sorted)
  fallback_tags = youtube_tags(group_title, sorted)
  generated_title = compact_text(ai['title'])
  final_title = generated_title.empty? ? group_title : generated_title

  meta = {
    'layout' => 'post',
    'title' => final_title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => sanitize_categories(ai['categories'], fallback_categories),
    'tags' => sanitize_tags(ai['tags'], fallback_tags),
    'youtube_id' => primary['videoId'],
    'description' => safe_excerpt(ai['description'].to_s.empty? ? youtube_description(group_title, sorted) : ai['description'].to_s),
    'seo_title' => compact_text(ai['seo_title']).empty? ? "#{final_title} | #{site_config['title']}" : compact_text(ai['seo_title']),
    'published' => false,
    'status' => 'draft'
  }

  body = ai['body'].to_s.strip
  body = build_youtube_post(group_title, sorted, site_config).last if body.empty?

  [date, build_front_matter(meta), body]
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

    date, front_matter, body =
      if ai_enabled?(options)
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
      site_description: site_config['description'],
      primary_keywords: title_keywords(options[:title] || topic),
      uniqueness_seed: Digest::SHA256.hexdigest("#{topic}-#{source_name}-#{date}")[0, 16]
    },
    options: options
  )

  title = compact_text(ai['title']).empty? ? (options[:title] || topic) : compact_text(ai['title'])
  fallback_categories = (options[:categories] || 'news, tech').split(',').map(&:strip).reject(&:empty?)
  fallback_tags = (options[:tags] || title_keywords(topic).join(',')).split(',').map(&:strip).reject(&:empty?).first(10)

  meta = {
    'layout' => 'post',
    'title' => title,
    'date' => "#{date} 12:00:00 +0800",
    'categories' => sanitize_categories(ai['categories'], fallback_categories),
    'tags' => sanitize_tags(ai['tags'], fallback_tags),
    'description' => safe_excerpt(ai['description'].to_s.empty? ? "A practical, reader-friendly update on #{topic}." : ai['description'].to_s),
    'seo_title' => compact_text(ai['seo_title']).empty? ? "#{title} | #{site_config['title']}" : compact_text(ai['seo_title']),
    'published' => false,
    'status' => 'draft'
  }

  body = ai['body'].to_s.strip
  body = build_news_post(options, site_config).last if body.empty?

  [date, title, build_front_matter(meta), body]
end

def run_news(options)
  site_config = load_site_config
  date, title, front_matter, body =
    if ai_enabled?(options)
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