---
layout: page
title: "Portfolio"
description: "Portfolio of KENJI (Kent Harvey Plando), including systems work, server deployment, and the METAXENOPY creator brand."
permalink: /portfolio/
---

This page highlights my professional background, technical experience, and creator-focused projects. For collaboration or questions, please use the [contact page]({{ '/contact/' | relative_url }}).

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
      <a href="{{ item.link }}" {% if item.link contains 'http' %}target="_blank" rel="noopener"{% endif %} class="btn btn-primary">{{ item.link_text }}</a>
      {% endif %}
    </div>
  </div>
{% endfor %}
</div>
