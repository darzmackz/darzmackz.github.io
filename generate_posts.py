#!/usr/bin/env python3
"""
YouTube to Blog Post Generator
Fetches videos from YouTube channel and generates Jekyll blog posts.
"""

import os
import re
from datetime import datetime
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

# YouTube API setup
API_KEY = 'AIzaSyDYATaFJt6DEgD1UJ56DFrC2RtabNCrSCc'
CHANNEL_ID = 'UCfvPH1Yj7Jaruk__YpTqciA'
UPLOADS_PLAYLIST_ID = 'UU' + CHANNEL_ID[2:]  # Replace UC with UU

def get_youtube_service():
    return build('youtube', 'v3', developerKey=API_KEY)

def fetch_all_videos():
    youtube = get_youtube_service()
    videos = []
    next_page_token = None

    while True:
        request = youtube.playlistItems().list(
            part='snippet',
            playlistId=UPLOADS_PLAYLIST_ID,
            maxResults=50,
            pageToken=next_page_token
        )
        response = request.execute()

        for item in response['items']:
            video_id = item['snippet']['resourceId']['videoId']
            title = item['snippet']['title']
            published_at = item['snippet']['publishedAt']
            videos.append({
                'id': video_id,
                'title': title,
                'published_at': published_at
            })

        next_page_token = response.get('nextPageToken')
        if not next_page_token:
            break

    return videos

def clean_title(title):
    # Remove suffixes like [Lyrics], [Karaoke], etc.
    title = re.sub(r'\s*\[.*?\]$', '', title).strip()
    return title

def group_videos(videos):
    groups = {}
    for video in videos:
        base_title = clean_title(video['title'])
        if base_title not in groups:
            groups[base_title] = []
        groups[base_title].append(video)

    # Sort videos in each group by published_at
    for base_title in groups:
        groups[base_title].sort(key=lambda x: x['published_at'])

    return groups

def parse_title_parts(title):
    # Parse artist and song from title like "Artist - Song (Details)"
    match = re.match(r'^(.+?)\s*-\s*(.+?)(?:\s*\(.+\))?$', title)
    if match:
        artist = match.group(1).strip()
        song = match.group(2).strip()
        return artist, song
    return None, title

def generate_post(base_title, video_group):
    # Use the earliest publish date
    publish_date = min(video['published_at'] for video in video_group)
    dt = datetime.fromisoformat(publish_date.replace('Z', '+00:00'))
    date_str = dt.strftime('%Y-%m-%d')
    datetime_str = dt.strftime('%Y-%m-%d %H:%M:%S %z')

    artist, song = parse_title_parts(base_title)

    # Categories and tags
    categories = ['Music']
    tags = []
    if artist:
        tags.append(artist.lower().replace(' ', '-'))
    if song:
        tags.append(song.lower().replace(' ', '-'))

    has_lyrics = any('[lyrics]' in v['title'].lower() for v in video_group)
    has_karaoke = any('[karaoke]' in v['title'].lower() or '[video]' in v['title'].lower() for v in video_group)

    if has_lyrics:
        categories.append('Lyrics')
        tags.append('lyrics')
    if has_karaoke:
        categories.append('Karaoke')
        tags.append('karaoke')

    # Excerpt
    excerpt = f"Watch {base_title} on METAXENOPY. "
    if has_lyrics and has_karaoke:
        excerpt += "Includes both lyrics video and karaoke version for the ultimate singing experience."
    elif has_lyrics:
        excerpt += "Official lyrics video with synchronized text."
    elif has_karaoke:
        excerpt += "Karaoke version perfect for singing along."

    # Content
    content = f"""Dive into "{base_title}" by {artist or 'the artist'}! This track brings the vibes you need.

"""

    lyrics_video = next((v for v in video_group if '[lyrics]' in v['title'].lower()), None)
    karaoke_video = next((v for v in video_group if '[karaoke]' in v['title'].lower() or '[video]' in v['title'].lower()), None)

    if lyrics_video:
        content += f"""## Lyrics Video
Watch the official lyrics video here:

<iframe width="560" height="315" src="https://www.youtube.com/embed/{lyrics_video['id']}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

"""

    if karaoke_video:
        content += f"""## Karaoke Version
Want to sing along? Try the karaoke version here:

<iframe width="560" height="315" src="https://www.youtube.com/embed/{karaoke_video['id']}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

"""

    # Front matter
    front_matter = f"""---
layout: post
title: "{base_title}"
date: {datetime_str}
categories: {categories}
tags: {tags}
excerpt: "{excerpt}"
---

"""

    return front_matter + content

def main():
    videos = fetch_all_videos()
    groups = group_videos(videos)

    posts_dir = '_posts'
    os.makedirs(posts_dir, exist_ok=True)

    for base_title, video_group in groups.items():
        post_content = generate_post(base_title, video_group)
        # Create filename
        dt = datetime.fromisoformat(video_group[0]['published_at'].replace('Z', '+00:00'))
        date_str = dt.strftime('%Y-%m-%d')
        filename = f"{date_str}-{base_title.lower().replace(' ', '-').replace('(', '').replace(')', '').replace(',', '').replace('\'', '')}.md"
        filepath = os.path.join(posts_dir, filename)

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(post_content)

        print(f"Generated post: {filepath}")

if __name__ == '__main__':
    main()