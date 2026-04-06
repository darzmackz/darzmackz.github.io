---
layout: post
title: "Getting Started with Jekyll on GitHub Pages"
date: "2025-01-15 12:00:00 +0800"
categories: [tutorial, web-dev]
tags: [jekyll, github-pages, static-site]
description: "Learn how to quickly build and publish a fast static website using Jekyll and GitHub Pages, including setup, structure, and deployment basics."
seo_title: "Jekyll Tutorial: Build and Deploy a Static Website with GitHub Pages"
status: "published"
published: true
---

Jekyll is one of the simplest ways to publish a fast static website, especially when you want to deploy on GitHub Pages without a complex backend.

## What You'll Learn

- what Jekyll is and why it works well for blogs
- how to install it locally
- how the basic folder structure fits together
- how to publish to GitHub Pages

## Why Jekyll Works Well

Jekyll turns Markdown, layouts, and data files into a complete static site. That makes it a good fit for personal websites, blogs, and documentation pages where speed and low maintenance matter.

It is also a practical choice if you want a site that is easy to audit. You can keep posts in plain files, manage templates directly, and see exactly what is being published without a heavy CMS in the middle.

## Quick Start

```bash
gem install bundler jekyll
jekyll new my-blog
cd my-blog
bundle exec jekyll serve
```

After that, open `http://localhost:4000` to preview the site locally.

## Core Folder Structure

The most common folders you will work with are:

- `_layouts` for page templates
- `_includes` for reusable partials
- `_posts` for blog posts
- `assets` for CSS, JavaScript, and images

## Publishing on GitHub Pages

1. Push the repository to GitHub.
2. Enable GitHub Pages in the repository settings.
3. Let GitHub build and publish the site from the selected branch.

That is often enough to launch a reliable personal site with minimal overhead.

## When Jekyll Is A Good Fit

Jekyll works best when:

- you want a blog or personal site with a stable structure
- you are comfortable editing Markdown and templates directly
- you prefer low hosting complexity over dynamic site features

If your main goal is publishing clear pages, owning your content, and keeping the site lightweight, Jekyll is still a strong option.

For a live example of how I use it, see the [homepage]({{ '/' | relative_url }}) and [portfolio]({{ '/portfolio/' | relative_url }}).
