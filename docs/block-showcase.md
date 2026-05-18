# Block Showcase — All 70 Blocks Demo

This page documents every block available in the Ensodo CMS builder with usage examples.

## How to View the Demo

Open the Block Showcase page in the builder:
**Admin > Sites > [Your Site] > Pages > Block Showcase**

Switch between **Visual** and **Wireframe** modes to see all blocks rendering.

---

## Section 1: Typography (13 blocks)

### heading
Renders heading text (h1-h6) with inline editing.
```json
{ "text": "Welcome to Ensodo", "level": "h1", "color": "", "fontSize": "" }
```

### paragraph
Rich text paragraph with WYSIWYG editing.
```json
{ "content": "<p>Your paragraph content here with <strong>bold</strong> and <em>italic</em>.</p>" }
```

### text
Simple text block with typography controls.
```json
{ "content": "Plain text content here." }
```

### rich-text
Full HTML editor — headings, lists, links, formatting.
```json
{ "content": "<h2>Title</h2><p>Content with <a href=\"#\">links</a>.</p>" }
```

### runningtext
Multi-column flowing text (newspaper style).
```json
{ "content": "Long text content...", "columns": 2, "columnGap": "40px", "columnRule": false }
```

### dropcap
First letter enlarged (magazine style).
```json
{ "content": "<p>Lorem ipsum dolor sit amet...</p>", "capSize": 3, "capColor": null }
```

### pullquote
Highlighted quote with attribution.
```json
{ "text": "Design is not just what it looks like.", "attribution": "Steve Jobs", "style": "large-text" }
```

### caption
Figure caption text.
```json
{ "text": "Fig. 1 — Architecture diagram", "prefix": "Fig." }
```

### footnote
Reference note with marker.
```json
{ "content": "Additional context for the reader.", "marker": "1" }
```

### sidenote
Marginal note (left or right).
```json
{ "content": "This is a side annotation.", "side": "right" }
```

### list
Bullet, numbered, or checklist.
```json
{ "items": ["First item", "Second item", "Third item"], "listType": "bullet" }
```

### code
Syntax-highlighted code block.
```json
{ "code": "const x = 42;", "language": "javascript", "show_line_numbers": true }
```

### textdivider
Decorative text separator.
```json
{ "style": "line", "customSymbol": "", "width": "half" }
```

---

## Section 2: Media (10 blocks)

### image
Single image with optional caption.
```json
{ "src": "/images/photo.jpg", "alt": "Description", "caption": "", "width": "", "height": "" }
```

### imagecaption
Image with positioned caption overlay.
```json
{ "src": "/images/hero.jpg", "alt": "Hero", "caption": "Photo by John", "captionPosition": "below" }
```

### gallery
Image grid/masonry/carousel.
```json
{ "images": [{"src": "/img1.jpg", "alt": "One"}, {"src": "/img2.jpg", "alt": "Two"}], "layout": "grid", "columns": 3, "gap": "8px" }
```

### fullbleed
Full-width image with text overlay.
```json
{ "src": "/images/wide.jpg", "alt": "Banner", "overlayText": "Welcome", "overlayPosition": "center", "scrimOpacity": 0.4 }
```

### video
Video embed (YouTube, Vimeo, or file).
```json
{ "url": "https://youtube.com/watch?v=...", "autoplay": false, "muted": false, "poster": "" }
```

### audio
Audio player with metadata.
```json
{ "url": "/audio/track.mp3", "title": "Episode 1", "artist": "Ensodo" }
```

### beforeafter
Slider comparing two images.
```json
{ "beforeSrc": "/before.jpg", "afterSrc": "/after.jpg", "beforeLabel": "Before", "afterLabel": "After", "initialPosition": 50 }
```

### icon
Single Lucide icon.
```json
{ "name": "Star", "size": "48", "color": "#3b82f6" }
```

### logostrip
Scrolling logo carousel.
```json
{ "images": [{"src": "/logo1.svg", "alt": "Client 1"}], "speed": 30, "pauseOnHover": true }
```

---

## Section 3: Layout (8 blocks)

### spacer
Vertical spacing between blocks.
```json
{ "height": "md" }
```
Sizes: `xs` (8px), `sm` (16px), `md` (32px), `lg` (48px), `xl` (64px)

### divider
Horizontal line separator.
```json
{ "style": "solid", "color": "#e5e7eb", "thickness": "1px", "width": "100%", "alignment": "center" }
```

### columns
Legacy multi-column layout (use Row > Column instead for new pages).
```json
{ "columnCount": 2, "gap": "medium", "ratio": "equal", "stackBelow": "mobile" }
```

### container
Max-width wrapper.
```json
{ "maxWidth": "1200", "centered": true }
```

### grid
CSS Grid layout container.
```json
{ "templateColumns": "1fr 1fr", "templateRows": "auto", "gap": "16px", "autoFlow": "row" }
```

### group
Generic wrapper (div/section/article).
```json
{ "tag": "div" }
```

### stickysidebar
Two-column layout with one sticky column.
```json
{ "sidebarSide": "right", "sidebarWidth": "300px", "gap": "32px", "stickyOffset": "80px" }
```

### overlap
Layered/overlapping content.
```json
{ "offset_x": "20px", "offset_y": "-30px" }
```

---

## Section 4: Interactive (7 blocks)

### button
CTA button with multiple styles.
```json
{ "text": "Get Started", "url": "/signup", "style": "primary", "size": "lg", "target": "_self" }
```
Styles: `primary`, `secondary`, `outline`, `ghost`

### accordion
Collapsible FAQ/content sections.
```json
{ "items": [{"title": "Question?", "content": "<p>Answer.</p>"}], "multiOpen": false, "iconStyle": "arrow" }
```

### tabs
Tabbed content switcher.
```json
{ "tab_labels": ["Tab 1", "Tab 2", "Tab 3"], "style": "underline", "alignment": "left" }
```

### modal
Popup/overlay content trigger.
```json
{ "triggerText": "Open Modal", "title": "Modal Title", "size": "md" }
```

### tooltip
Hover tooltip on text.
```json
{ "triggerText": "Hover me", "tooltipText": "More information here", "position": "top" }
```

### toc
Auto-generated table of contents from headings.
```json
{ "maxDepth": 3, "style": "inline", "sticky": false }
```

### readingprogress
Scroll progress indicator.
```json
{ "style": "top-bar", "color": "#3b82f6", "height": "3px" }
```

---

## Section 5: Marketing (9 blocks)

### hero
Full hero section with background, title, CTA.
```json
{ "title": "Hero Title", "subtitle": "Subtitle text", "cta_text": "Get Started", "cta_url": "#", "background_color": "#1e293b" }
```

### ctabanner
Call-to-action banner.
```json
{ "heading": "Ready to start?", "text": "Join thousands of users.", "buttonText": "Sign Up", "buttonUrl": "/signup" }
```

### pricingcard
Single pricing plan card.
```json
{ "planName": "Pro", "price": "$29", "period": "month", "features": [{"text": "Unlimited", "included": true}], "ctaText": "Choose", "ctaUrl": "#" }
```

### pricingtable
Multi-plan comparison table.
```json
{ "plans": [{"name": "Basic", "price": "$9", "period": "/mo", "features": ["Feature 1"], "ctaText": "Choose"}], "columns": 3 }
```

### testimonial
Customer quotes.
```json
{ "items": [{"quote": "Amazing product!", "author": "Jane", "role": "CEO", "avatar": ""}], "layout": "single" }
```

### featuregrid
Icon + title + description grid.
```json
{ "items": [{"icon": "star", "title": "Fast", "description": "Lightning fast."}], "columns": 3, "style": "icon-top" }
```

### featurecomparison
Plan feature comparison matrix.
```json
{ "plans": [{"name": "Basic", "price": "$9"}, {"name": "Pro", "price": "$29"}], "features": [{"name": "Storage", "values": ["5GB", "50GB"]}] }
```

### stats
Key metrics display.
```json
{ "items": [{"value": "10K", "label": "Users", "prefix": "", "suffix": "+"}], "columns": 3 }
```

### timeline
Chronological event list.
```json
{ "items": [{"date": "2024", "title": "Founded", "description": "Company started."}], "layout": "left" }
```

---

## Section 6: Blog (9 blocks)

### postgrid
Grid of blog posts (fetched from database).
```json
{ "categoryId": "", "limit": 6, "columns": 3, "cardStyle": "vertical", "showExcerpt": true }
```

### latestposts
Recent posts feed.
```json
{ "count": 4, "categoryId": "", "showExcerpt": true, "showImage": true }
```

### postcard
Single post preview card.
```json
{ "postId": "", "style": "vertical", "showExcerpt": true, "showDate": true }
```

### categorylist
Blog category listing.
```json
{ "style": "links", "showCount": true, "parentOnly": false }
```

### authorbox
Author bio section.
```json
{ "showAvatar": true, "showBio": true, "showSocialLinks": false, "layout": "horizontal" }
```

### relatedposts
Related posts based on category/tags.
```json
{ "limit": 3, "basedOn": "category" }
```

### newsletter
Email subscription form.
```json
{ "heading": "Subscribe", "buttonText": "Subscribe", "placeholder": "your@email.com" }
```

### sharebuttons
Social sharing buttons.
```json
{ "platforms": ["twitter", "facebook", "linkedin", "email", "copy"], "style": "icons", "showLabels": false }
```

### paywall
Content gate with CTA.
```json
{ "heading": "Subscribe to continue", "ctaText": "Subscribe", "ctaUrl": "#", "previewLines": 3, "blurIntensity": 8 }
```

---

## Section 7: Forms (2 blocks)

### contact-form
Standard contact form.
```json
{ "fields": [{"label": "Name", "type": "text", "required": true}, {"label": "Email", "type": "email", "required": true}, {"label": "Message", "type": "textarea", "required": true}], "submitText": "Send" }
```

### customform
Fully configurable form builder.
```json
{ "fields": [{"type": "text", "label": "Company", "required": true, "placeholder": "Your company"}], "submitText": "Submit" }
```

---

## Section 8: Embeds & Advanced (10 blocks)

### html-embed
Raw HTML/script injection.
```json
{ "code": "<div class=\"custom\">Any HTML here</div>", "sandbox": false }
```

### socialembed
Social media embed (Twitter, YouTube, Instagram).
```json
{ "url": "https://twitter.com/...", "platform": "auto" }
```

### map
Google Maps / address embed.
```json
{ "address": "Sofia, Bulgaria", "zoom": 13, "height": "400px" }
```

### chart
Data visualization (bar, line, pie).
```json
{ "chartType": "bar", "data": [{"label": "Q1", "value": 30}, {"label": "Q2", "value": 70}], "title": "Revenue" }
```

### table
Data table with headers.
```json
{ "headers": ["Name", "Role", "Status"], "rows": [["John", "Dev", "Active"]], "striped": true }
```

### flipbook
PDF page-flip viewer.
```json
{ "mode": "realistic", "aspect_ratio": "2:3", "pdf_asset_id": null }
```

### scroll_page
Full scroll-based storytelling page.
```json
{ "typography": {...}, "palette": {...}, "layout": {...} }
```

### menu
Navigation menu renderer.
```json
{ "menuId": "", "style": "horizontal", "orientation": "horizontal" }
```

### breadcrumbs
Page path navigation.
```json
{ "separator": "/", "showHome": true, "homeLabel": "Home", "showCurrent": true }
```

### anchormenu
Sticky in-page navigation.
```json
{ "items": [{"label": "Section 1", "anchor": "#section-1"}], "style": "pills", "sticky": true }
```
