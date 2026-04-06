---
layout: page
title: "Portfolio"
description: "Portfolio of KENJI (Kent Harvey Plando), including systems work, server deployment, and the METAXENOPY creator brand."
permalink: /portfolio/
---

This page highlights the work I can actually stand behind: systems support, server and infrastructure tasks, and the creator projects I maintain under the METAXENOPY name. If you want the broader story behind the site, visit the [About page]({{ '/about/' | relative_url }}). For direct inquiries, use the [contact page]({{ '/contact/' | relative_url }}).

## What I Work On

My background is split between two areas that support each other well.

- Day-to-day technical work involving databases, servers, networking, support, and troubleshooting.
- Creator and publishing work involving lyric videos, karaoke uploads, and maintaining this public website.

That combination matters because a lot of creator work becomes more sustainable when the technical side is handled carefully. I approach deployments, site maintenance, and content structure with the same mindset: keep things readable, stable, and easy to maintain.

## Selected Experience

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

## What This Portfolio Shows

- Practical systems experience, not just tool lists.
- A maintained public website that supports the creator brand with clear ownership and navigation.
- Ongoing music-related publishing through the METAXENOPY channel and blog.

If you want to see the publishing side in action, head to the [blog]({{ '/blog/' | relative_url }}). If you want a quick summary of who I am and how the site is structured, go back to the [homepage]({{ '/' | relative_url }}).
