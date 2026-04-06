# AI Codex Prompt: Fix AdSense Low Value Content

Use this prompt in Codex or another coding agent to improve the repository so it is more likely to pass AdSense review.

---

You are working in the Jekyll repository darzmackz.github.io.

Goal: fix the root causes behind the AdSense low value content warning by turning the site into a clearly useful, original, well-structured publisher site with substantial content, strong internal navigation, and no thin or template-like public pages.

Context you must use:
- The site is a personal brand site for KENJI / METAXENOPY.
- The repository contains many post files under _posts/ that still include AI_DRAFT_PROMPT scaffolding, placeholder content, or draft-style text.
- The blog archive already filters posts with published: false, so unpublished drafts should stay out of the public archive.
- The homepage, about page, portfolio page, contact page, privacy page, terms page, editorial policy page, and disclosure page are already part of the trust structure.

What to fix:
1. Audit the public site for thin, repetitive, or low-value content.
2. Replace template-like or placeholder public content with unique, useful writing.
3. Remove AI_DRAFT_PROMPT scaffolding from any public-facing post that is meant to be published.
4. Keep unfinished or duplicate post drafts unpublished so they do not appear as low-value pages.
5. Improve the homepage and blog archive so they highlight real value, clear ownership, and genuine topical depth instead of repeating policy language.
6. Strengthen the about, portfolio, and blog pages with more specific, original, reader-useful content about the publisher’s background, music work, and technical experience.
7. Make sure each public article has a clear topic, useful body content, and a distinct angle. Avoid one-paragraph filler, repetitive song titles with no added value, or near-duplicate posts that only differ by small metadata changes.
8. If multiple posts cover almost the same subject, consolidate them into one stronger page or keep only the best version public.
9. Add or improve internal links between homepage, about, portfolio, blog, and policy pages so the site feels complete and easy to navigate.
10. Preserve the existing Jekyll structure and styling unless changes are needed to support better content quality.

Specific editing guidance:
- For each public post, ensure the content includes a meaningful introduction, a unique body, and a closing that adds value for readers.
- Replace generic AI-generated filler with concrete commentary tied to the song, creator brand, technical experience, or the page topic.
- Do not invent facts.
- Do not add copyrighted lyrics or other protected text.
- Do not leave placeholder sections like CONTENT, SOURCE_DATA, or instructions for future AI editing in published pages.
- If a page is not ready for publication, mark it unpublished or keep it out of the public build.
- Keep metadata accurate and consistent with the actual content.
- Use concise, natural language that reads like a real publisher wrote it.

Recommended deliverables:
- A stronger homepage with clearer real-world value and fewer generic trust statements.
- Improved about and portfolio copy with more substance.
- Cleaned public posts with removed scaffolding and more original content.
- Better archive discoverability and clearer topical organization.
- Any small layout or metadata improvements needed to support the content changes.

Acceptance criteria:
- Public pages feel genuinely useful and original.
- No public page should look like a thin draft or template shell.
- The site should clearly show who publishes it, what it covers, and why visitors should spend time on it.
- The content should be strong enough to reasonably support an AdSense review request.

Work method:
- Inspect the repository first.
- Identify the worst offending pages and patterns.
- Make the smallest set of changes that meaningfully improves site quality.
- Prefer root-cause fixes over cosmetic rewrites.
- Validate the result before finishing.

When you are done, summarize:
- which pages were changed,
- what thin-content patterns were removed or improved,
- and any files that should remain unpublished.