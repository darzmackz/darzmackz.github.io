# AI Coding Guidelines for darzmackz.github.io

## Project Overview
This is a Jekyll-powered static site for a personal blog and portfolio, focusing on tech insights, Filipino music lyrics, and OPM karaoke content. The site uses GitHub Pages for free hosting and includes dark/light theme support.

## Architecture & Structure
- **Jekyll Framework**: Static site generator with Liquid templating
- **Key Directories**:
  - `_posts/`: Blog content in Markdown with front matter
  - `_layouts/`: HTML templates (default.html, post.html, page.html)
  - `_includes/`: Reusable components (header.html, footer.html, adsense-*.html)
  - `_data/`: Structured data (portfolio.json)
  - `assets/`: CSS (main.css) and JS (main.js)
- **Data Flow**: Posts → Layouts → Includes → Assets

## Content Creation Patterns
### Blog Posts
- **Filename Format**: `YYYY-MM-DD-title-slug.md` (e.g., `2025-01-15-getting-started-with-jekyll.md`)
- **Front Matter Fields**:
  - `layout: post`
  - `title: "Post Title"`
  - `date: YYYY-MM-DD HH:MM:SS +0000`
  - `categories: [array]` (e.g., `[tutorial, web-dev]`)
  - `tags: [array]` (e.g., `[jekyll, github-pages]`)
  - `excerpt: "Brief description"`
  - `youtube_id: "VIDEO_ID"` (optional, for embedded videos)
- **YouTube Integration**: Add `youtube_id` to front matter for automatic iframe embedding in post layout

### Pages
- **Front Matter**: `layout: page`, `title`, `description`, `permalink: /custom-path/`
- **Portfolio Page**: Pulls data from `_data/portfolio.json` using `{% for item in site.data.portfolio %}`

### Portfolio Data
- **Format**: JSON array in `_data/portfolio.json`
- **Fields**: `id`, `icon`, `title`, `subtitle`, `description`, `tech` (array), `link`, `link_text`
- **Usage**: Loop in portfolio.md to generate cards with tech badges and links

## Styling Conventions
- **CSS Variables**: Extensive use of CSS custom properties for theming (`--color-primary`, `--font-sans`, etc.)
- **Themes**: Light/dark mode with `[data-theme]` attribute
- **Fonts**: Inter for body text, JetBrains Mono for code
- **Responsive**: Mobile-first with container max-width of 1100px

## JavaScript Patterns
- **IIFE Modules**: All JS wrapped in immediately invoked function expressions
- **Theme Toggle**: Saves preference to localStorage, respects system preference
- **Mobile Nav**: Toggle class and ARIA attributes for accessibility

## Development Workflow
- **Local Preview**: `bundle exec jekyll serve` (requires Ruby/Bundler)
- **Build**: `bundle exec jekyll build`
- **Deploy**: Push to GitHub main branch (GitHub Pages auto-builds)
- **Fetch YouTube Posts**: Run `ruby scripts/fetch_youtube.rb` with YOUTUBE_API_KEY env var
- **Dependencies**: Managed via Gemfile (Jekyll 4.3+, plugins for feed/seo/sitemap, google-api-client for YouTube)

## Integration Points
- **AdSense**: Publisher ID in `_config.yml`, ad slots in includes (header, footer, post)
- **Social Links**: Configured in `_config.yml` (GitHub, Twitter, Facebook, YouTube)
- **SEO**: Jekyll-seo-tag plugin for meta tags, structured data for videos, sitemap link in head
- **YouTube Integration**: Scripts to fetch and generate posts from channel videos, combining lyrics and karaoke versions
- **Admin Panel**: Static HTML interface for editing posts, portfolio, pages, and site config via GitHub API

## Key Files to Reference
- `_config.yml`: Site configuration, AdSense, social links, customization (favicon, logo, primary_color)
- `_layouts/post.html`: Post rendering with YouTube embeds and structured data
- `_includes/header.html`: Navigation and theme toggle
- `assets/css/main.css`: Theme variables and responsive styles
- `_data/portfolio.json`: Structured portfolio data
- `admin/index.html`: Admin panel for content management
- `scripts/fetch_youtube.rb`: Script to populate blog posts from YouTube channel</content>
<parameter name="filePath">c:\GITHUB PROJECTS\darzmackz.github.io\.github\copilot-instructions.md