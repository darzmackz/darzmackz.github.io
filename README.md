# darzmackz.github.io

Personal website and blog for KENJI / METAXENOPY, built with Jekyll and hosted on GitHub Pages.

## Includes

- homepage, about, portfolio, blog, and contact pages
- privacy, terms, editorial policy, and advertising disclosure pages
- AdSense account integration via site config and `ads.txt`
- Google Search Console verification support via `_config.yml`
- Jekyll SEO, feed, and sitemap plugins
- admin workflow for posts, pages, media, settings, and publishing checks
- Ruby-based content automation script for YouTube and news draft generation

## Content Automation

Use [`scripts/publish_content.rb`](scripts/publish_content.rb) to generate structured blog drafts:

- `ruby scripts/publish_content.rb youtube --input data/youtube.json`
- `ruby scripts/publish_content.rb youtube --api-key YOUR_KEY --channel-id YOUR_CHANNEL_ID`
- `ruby scripts/publish_content.rb news --topic "Zero trust access update" --source-name "Vendor Advisory" --source-url "https://example.com/advisory"`
- `ruby scripts/publish_content.rb youtube --input data/youtube.json --use-ai --openai-api-key YOUR_OPENAI_API_KEY`
- `ruby scripts/publish_content.rb news --topic "Zero trust access update" --source-name "Vendor Advisory" --source-url "https://example.com/advisory" --use-ai --openai-api-key YOUR_OPENAI_API_KEY`

The YouTube flow groups matching `[Lyrics]` and `[Karaoke]` uploads into a single blog draft when they share the same base performance title, uses the upload date for the post date, and skips duplicates when a matching post already exists.
If `--use-ai` is enabled, the script asks OpenAI to generate a more unique SEO-focused title, description, tags, categories, and Markdown body instead of relying only on the built-in fallback templates.
