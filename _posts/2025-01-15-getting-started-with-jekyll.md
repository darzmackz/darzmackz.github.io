---
layout: post
title: "Getting Started with Jekyll on GitHub Pages"
date: 2025-01-15 10:00:00 +0000
categories: [tutorial, web-dev]
tags: [jekyll, github-pages, static-site]
excerpt: "In this tutorial I walk you through setting up a Jekyll blog site from scratch and deploying it to GitHub Pages — completely free."
---

In this post, I walk you through **setting up a Jekyll site on GitHub Pages** from absolute zero — no prior Ruby or Jekyll experience required.

## What You'll Learn

- What Jekyll is and why it's great for blogs
- How to install Jekyll locally and preview your site
- The basic folder structure (`_layouts`, `_includes`, `_posts`)
- How to write your first blog post in Markdown
- How to deploy everything to GitHub Pages for free

## Why Jekyll?

Jekyll is a **static site generator** — it turns plain Markdown and HTML templates into a ready-to-deploy website. GitHub Pages has built-in support for Jekyll, so you can push your source files and GitHub will automatically build and host your site.

Key benefits:

- **Free hosting** on `username.github.io`
- **Fast** — static HTML files, no database
- **Simple** — write posts in Markdown
- **Customizable** — full control over HTML/CSS

## Quick Start

If you just want to get going locally, here are the key commands:

```bash
# Install Jekyll
gem install bundler jekyll

# Create a new site
jekyll new my-blog

# Serve it locally
cd my-blog
bundle exec jekyll serve
```

Then open [http://localhost:4000](http://localhost:4000) in your browser and you'll see your site live.

## Creating a Post

Blog posts live in the `_posts` directory and follow this naming convention:

```
_posts/YYYY-MM-DD-title-with-hyphens.md
```

Every post starts with **front matter** — a YAML block at the top:

```yaml
---
layout: post
title: "My First Post"
date: 2025-01-15 10:00:00 +0000
categories: [blog]
tags: [welcome]
---
```

After the front matter, write the content in Markdown.

## Deploying to GitHub Pages

1. Push your Jekyll project to a GitHub repository named `username.github.io`.
2. Go to **Settings → Pages** and set the source to your main branch.
3. GitHub will build and publish the site automatically.

That's it! 🎉

## Watch on YouTube

Check out the [METAXENOPY YouTube channel](https://www.youtube.com/@metaxenopy?sub_confirmation=1) for video tutorials and more content.

---

Have questions? [Get in touch]({{ '/contact/' | relative_url }}) or leave a comment below!
