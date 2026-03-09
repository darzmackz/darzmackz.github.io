---
layout: page
title: "Portfolio"
description: "Portfolio of KENJI (Kent Harvey Plando) — Senior System Analyst, and creator of METAXENOPY, a YouTube channel for Filipino lyrics, OPM karaoke, and international music videos."
permalink: /portfolio/
---

Here's a snapshot of my professional background, technical expertise, and creative work — including my **METAXENOPY** YouTube channel featuring lyrics and karaoke content.
Feel free to [reach out]({{ '/contact/' | relative_url }}) if you'd like to collaborate or learn more!

<div class="portfolio-grid">
{% for item in site.data.portfolio %}
  <div class="portfolio-card">
    <div class="portfolio-card-image">{{ item.icon }}</div>
    <div class="portfolio-card-body">
      <h3>{{ item.title }}</h3>
      {% if item.subtitle != "" %}<p>{{ item.subtitle }}</p>{% endif %}
      <p>{{ item.description }}</p>
      {% if item.tech.size > 0 %}
      <div class="tech-stack">
        {% for badge in item.tech %}<span class="tech-badge">{{ badge }}</span>{% endfor %}
      </div>
      {% endif %}
      {% if item.link != "" and item.link_text != "" %}
      <a href="{{ item.link }}" {% unless item.link contains 'http' %}{% else %}target="_blank" rel="noopener"{% endunless %} class="btn btn-primary">{{ item.link_text }}</a>
      {% endif %}
    </div>
  </div>
{% endfor %}
</div>

---

> 💡 **More coming soon.** [Contact me]({{ '/contact/' | relative_url }}) if you'd like to collaborate!

