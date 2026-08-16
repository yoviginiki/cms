#!/bin/bash
cd /home/cytechno/web/agg.artday.bg/public_html
: > /tmp/enrest.jsonl
python3 - <<'PY' > /tmp/torich.tsv
import json
wl=set(l.strip() for l in open('/tmp/whitelist.txt') if l.strip())
rows=[]
for fn,typ in [('/tmp/museums.json','Музей'),('/tmp/kc.jsonx','Културен център')]:
    for m in json.load(open(fn)):
        eid="opoznai-"+m['slug'][:50]
        if eid in wl: rows.append((eid,m['slug'],m['name'].strip(' "').replace('"','„')[:120],m.get('city') or 'София',typ))
open('/tmp/torich.tsv','w').write('\n'.join('\t'.join(r) for r in rows))
print(len(rows))
PY
while IFS=$'\t' read -r eid slug name city typ; do
  [ -z "$slug" ] && continue
  echo "{\"url\":\"https://opoznai.bg/view/$slug\",\"waitMs\":1800,\"timeout\":40000}" | timeout 50 node browser/render.mjs 2>/dev/null > /tmp/er.json
  python3 - "$eid" "$name" "$city" "$typ" "$slug" >> /tmp/enrest.jsonl <<'PY'
import json,sys,re
eid,name,city,typ,slug=sys.argv[1:6]
try: h=json.load(open('/tmp/er.json')).get('html') or ''
except: sys.exit()
def meta(p):
    m=re.search(r'<meta[^>]+(?:property|name)=["\']'+p+r'["\'][^>]+content=["\']([^"\']+)',h,re.I); return m.group(1).strip() if m else None
img=meta('og:image'); desc=meta('og:description') or meta('description')
v={"external_id":eid,"title":name,"type":typ,"city":city,"official_url":"https://opoznai.bg/view/"+slug}
if img and img.startswith('http'): v["image_url"]=img
if desc and len(desc)>30: v["description"]=desc[:600]
print(json.dumps(v,ensure_ascii=False))
PY
done < /tmp/torich.tsv
echo "REST_DONE lines=$(wc -l < /tmp/enrest.jsonl)"
