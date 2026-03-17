#!/usr/bin/env ruby

require 'google/apis/youtube_v3'
require 'yaml'
require 'date'

# Set up YouTube API
YOUTUBE_API_KEY = ENV['YOUTUBE_API_KEY']
CHANNEL_ID = 'UC...' # Replace with actual channel ID for @metaxenopy

youtube = Google::Apis::YoutubeV3::YouTubeService.new
youtube.key = YOUTUBE_API_KEY

# Get channel uploads playlist
channel_response = youtube.list_channels('contentDetails', id: CHANNEL_ID)
uploads_playlist_id = channel_response.items.first.content_details.related_playlists.uploads

# Get all videos from uploads playlist
videos = []
next_page_token = nil

loop do
  playlist_response = youtube.list_playlist_items('snippet', playlist_id: uploads_playlist_id, max_results: 50, page_token: next_page_token)
  videos.concat(playlist_response.items)
  next_page_token = playlist_response.next_page_token
  break unless next_page_token
end

# Group videos by base title (remove [Lyrics] or [Karaoke])
grouped = {}

videos.each do |item|
  title = item.snippet.title
  published_at = item.snippet.published_at
  video_id = item.snippet.resource_id.video_id

  # Extract base title
  base_title = title.gsub(/\s*\[Lyrics\]|\s*\[Karaoke\]/, '').strip

  grouped[base_title] ||= []
  grouped[base_title] << {
    title: title,
    date: published_at,
    id: video_id,
    type: title.include?('[Karaoke]') ? 'karaoke' : 'lyrics'
  }
end

# Create posts
grouped.each do |base_title, vids|
  if vids.size == 1
    # Single video
    vid = vids.first
    create_post(base_title, vid[:date], vid[:id], nil)
  elsif vids.size == 2
    # Combined
    lyrics = vids.find { |v| v[:type] == 'lyrics' }
    karaoke = vids.find { |v| v[:type] == 'karaoke' }
    create_combined_post(base_title, [lyrics, karaoke].compact.first[:date], lyrics&.[](:id), karaoke&.[](:id))
  end
end

def create_post(title, date, youtube_id, karaoke_id)
  date_str = Date.parse(date.to_s).strftime('%Y-%m-%d')
  filename = "#{date_str}-#{title.downcase.gsub(/[^a-z0-9]+/, '-').gsub(/^-|-$/, '')}.md"

  front_matter = {
    'layout' => 'post',
    'title' => "\"#{title}\"",
    'date' => "#{Date.parse(date.to_s).strftime('%Y-%m-%d %H:%M:%S %z')}",
    'categories' => '[music, lyrics]',
    'tags' => '[opm, filipino, karaoke]',
    'excerpt' => "\"Watch the lyrics video for #{title}.\""
  }

  if youtube_id
    front_matter['youtube_id'] = "\"#{youtube_id}\""
  end

  content = "Watch the lyrics video for #{title}.\n\n"

  if karaoke_id
    content += "## Karaoke Version\n\n"
    content += "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/#{karaoke_id}\" frameborder=\"0\" allowfullscreen></iframe>\n\n"
  end

  write_post(filename, front_matter, content)
end

def create_combined_post(title, date, lyrics_id, karaoke_id)
  date_str = Date.parse(date.to_s).strftime('%Y-%m-%d')
  filename = "#{date_str}-#{title.downcase.gsub(/[^a-z0-9]+/, '-').gsub(/^-|-$/, '')}.md"

  front_matter = {
    'layout' => 'post',
    'title' => "\"#{title}\"",
    'date' => "#{Date.parse(date.to_s).strftime('%Y-%m-%d %H:%M:%S %z')}",
    'categories' => '[music, lyrics, karaoke]',
    'tags' => '[opm, filipino, karaoke, lyrics]',
    'excerpt' => "\"Watch the lyrics video and karaoke version for #{title}.\""
  }

  content = "Watch the lyrics video and sing along with the karaoke version of #{title}.\n\n"

  if lyrics_id
    content += "## Lyrics Video\n\n"
    content += "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/#{lyrics_id}\" frameborder=\"0\" allowfullscreen></iframe>\n\n"
  end

  if karaoke_id
    content += "## Karaoke Version\n\n"
    content += "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/#{karaoke_id}\" frameborder=\"0\" allowfullscreen></iframe>\n\n"
  end

  write_post(filename, front_matter, content)
end

def write_post(filename, front_matter, content)
  path = "_posts/#{filename}"
  return if File.exist?(path)

  fm_yaml = front_matter.map { |k, v| "#{k}: #{v}" }.join("\n")

  File.write(path, "---\n#{fm_yaml}\n---\n\n#{content}")
  puts "Created #{path}"
end