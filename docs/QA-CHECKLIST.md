# Manual QA Checklist

**Last updated:** Sprint 11 (2026-06-14)

## Site Creation

- [ ] Open Dashboard → click "Create new site"
- [ ] Step 1: Enter site name + slug → Next works
- [ ] Step 2: Theme list loads, can select theme → Next works
- [ ] Step 3: Template options shown, wireframe preview displays → Next works
- [ ] Step 4: Confirm screen shows name/slug/theme/template correctly
- [ ] Create → site created successfully with success screen
- [ ] "Open Pages" navigates to site pages
- [ ] Site appears in Dashboard grid

## Themes

- [ ] Navigate to Themes page → theme list loads
- [ ] System themes shown (Editorial, Commerce, Bare)
- [ ] Click Activate → theme assignment succeeds (toast)
- [ ] Fork theme → name prompt, fork created, navigates to editor
- [ ] Theme Editor → token tree loads, color pickers work
- [ ] Import JSON → validates format, creates theme
- [ ] Export theme → downloads JSON

## Starter Templates

- [ ] Blog template creates: Home, About, Contact, Blog pages + 3 sample posts
- [ ] Portfolio template creates: Home, About, Work, Contact + 3 posts
- [ ] Business template creates: Home, About, Services, Team, Contact
- [ ] Blank template creates: Home page only
- [ ] Homepage set correctly in site settings

## Page Builder

- [ ] Open any page → builder loads
- [ ] Empty page shows "Add your first section" CTA
- [ ] Click Section Library → PresetBrowser opens with presets
- [ ] Insert section preset → section appears in canvas
- [ ] Select block → blue ring outline, toolbar appears above
- [ ] Move up/down buttons work
- [ ] Duplicate creates copy below
- [ ] Delete removes block
- [ ] Save as Template → toast "Saved as template"
- [ ] Undo/redo buttons work
- [ ] Device preview switches (Desktop/Tablet/Mobile)
- [ ] Right panel shows block settings when selected
- [ ] Right panel shows "Select a section..." when nothing selected
- [ ] Ctrl+S saves manually
- [ ] Auto-save triggers after 3s of inactivity
- [ ] Save status shows: Saving... → Saved HH:MM

## Section Library

- [ ] Presets tab shows 13 system presets
- [ ] Saved tab loads site templates
- [ ] Click preset → inserts into page
- [ ] Delete button on saved templates works
- [ ] Search filters both tabs

## Media

- [ ] Assets page loads with grid view
- [ ] Drag-drop upload works
- [ ] Click upload button works
- [ ] Search filters by filename
- [ ] Type filter (images/documents/video/audio) works
- [ ] Click asset → detail panel opens
- [ ] Dimensions shown for images
- [ ] Alt text field editable (saves on blur)
- [ ] Missing alt text warning shown
- [ ] Copy URL button works
- [ ] Download button works
- [ ] Delete with confirmation works

## SEO / Open Graph

- [ ] Page editor SEO tab → meta title, description, OG image fields
- [ ] SeoAnalyzer shows score and checks
- [ ] Missing meta description flagged
- [ ] Title length warning shown if too long/short
- [ ] Alt text warning for images without alt

## Publishing

- [ ] Publish button visible in page editor toolbar
- [ ] Click Publish → progress shown (Building X/Y)
- [ ] Success state auto-dismisses
- [ ] Dropdown → Full Publish / Quick Publish options
- [ ] Deployment history shows in dropdown
- [ ] Rollback button available on live deployments
- [ ] Error state shows error message

## AI Assistant

- [ ] AI button appears on text/heading/paragraph/hero/ctabanner blocks
- [ ] Generate → prompt modal opens, generates content
- [ ] Rewrite options (shorter, longer, simpler, etc.) work
- [ ] Translate → language picker works
- [ ] Suggestion shown with Accept/Discard
- [ ] Accept updates block content
- [ ] Disabled state if AI not configured (503 response)

## Permissions

- [ ] Non-authenticated users redirected to login
- [ ] Login form works with valid credentials
- [ ] Invalid credentials show error
- [ ] Admin can access all features
- [ ] Editor can edit content and publish

## Generated Frontend

- [ ] Published site loads at correct URL
- [ ] Homepage renders correctly
- [ ] Internal links work between pages
- [ ] Images load (asset URLs rewritten)
- [ ] Theme CSS applied (colors, fonts)
- [ ] Sitemap.xml exists and contains pages
- [ ] Robots.txt exists
- [ ] RSS feed.xml exists with posts
- [ ] OG meta tags present in source
- [ ] Mobile responsive (viewport meta, media queries)
