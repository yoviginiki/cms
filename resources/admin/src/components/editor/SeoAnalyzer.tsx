import { useMemo } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { AlertTriangle, CheckCircle, XCircle, Info, Search } from 'lucide-react';
import type { BlockData } from '@/types/blocks';

interface SeoCheck {
  id: string;
  label: string;
  status: 'pass' | 'warning' | 'error' | 'info';
  message: string;
}

interface SeoAnalyzerProps {
  pageTitle?: string;
  seoTitle?: string;
  seoDescription?: string;
  slug?: string;
}

function extractText(blocks: BlockData[]): string {
  let text = '';
  for (const block of blocks) {
    const data = block.data ?? {};
    if (data.text) text += ' ' + data.text;
    if (data.content) text += ' ' + String(data.content).replace(/<[^>]*>/g, '');
    if (data.heading) text += ' ' + data.heading;
    if (data.title) text += ' ' + data.title;
    if (data.subtitle) text += ' ' + data.subtitle;
    if (block.children?.length) text += ' ' + extractText(block.children);
  }
  return text.trim();
}

function countWords(text: string): number {
  return text.split(/\s+/).filter(w => w.length > 0).length;
}

function findBlocks(blocks: BlockData[], type: string): BlockData[] {
  const found: BlockData[] = [];
  for (const block of blocks) {
    if (block.type === type) found.push(block);
    if (block.children?.length) found.push(...findBlocks(block.children, type));
  }
  return found;
}

function findAllBlocks(blocks: BlockData[]): BlockData[] {
  const all: BlockData[] = [];
  for (const block of blocks) {
    all.push(block);
    if (block.children?.length) all.push(...findAllBlocks(block.children));
  }
  return all;
}

export function SeoAnalyzer({ pageTitle, seoTitle, seoDescription, slug }: SeoAnalyzerProps) {
  const blocks = useEditorStore((s) => s.blocks);

  const checks = useMemo<SeoCheck[]>(() => {
    const results: SeoCheck[] = [];
    const allBlocks = findAllBlocks(blocks);
    const text = extractText(blocks);
    const wordCount = countWords(text);

    // 1. Title tag
    const effectiveTitle = seoTitle || pageTitle || '';
    if (!effectiveTitle) {
      results.push({ id: 'title-missing', label: 'Page Title', status: 'error', message: 'No page title set. Search engines need a title to display in results.' });
    } else if (effectiveTitle.length > 60) {
      results.push({ id: 'title-long', label: 'Page Title', status: 'warning', message: `Title is ${effectiveTitle.length} characters. Keep under 60 for best display in search results.` });
    } else if (effectiveTitle.length < 20) {
      results.push({ id: 'title-short', label: 'Page Title', status: 'warning', message: `Title is only ${effectiveTitle.length} characters. Aim for 20-60 characters.` });
    } else {
      results.push({ id: 'title-ok', label: 'Page Title', status: 'pass', message: `Title is ${effectiveTitle.length} characters — good length.` });
    }

    // 2. Meta description
    if (!seoDescription) {
      results.push({ id: 'desc-missing', label: 'Meta Description', status: 'error', message: 'No meta description set. Add one to improve click-through from search results.' });
    } else if (seoDescription.length > 160) {
      results.push({ id: 'desc-long', label: 'Meta Description', status: 'warning', message: `Description is ${seoDescription.length} chars. Keep under 160 to avoid truncation.` });
    } else if (seoDescription.length < 50) {
      results.push({ id: 'desc-short', label: 'Meta Description', status: 'warning', message: `Description is only ${seoDescription.length} chars. Aim for 50-160 for best results.` });
    } else {
      results.push({ id: 'desc-ok', label: 'Meta Description', status: 'pass', message: `Description is ${seoDescription.length} chars — optimal length.` });
    }

    // 3. H1 tag (also count Hero blocks which always render an H1)
    const headings = findBlocks(blocks, 'heading');
    const h1s = headings.filter(h => h.data.level === 'h1');
    const heroBlocks = findBlocks(blocks, 'hero');
    const totalH1s = h1s.length + heroBlocks.length;
    if (totalH1s === 0) {
      results.push({ id: 'h1-missing', label: 'H1 Heading', status: 'error', message: 'No H1 heading found. Every page should have exactly one H1.' });
    } else if (totalH1s > 1) {
      results.push({ id: 'h1-multiple', label: 'H1 Heading', status: 'warning', message: `Found ${totalH1s} H1 headings. Use only one H1 per page.` });
    } else {
      results.push({ id: 'h1-ok', label: 'H1 Heading', status: 'pass', message: 'Page has exactly one H1 heading.' });
    }

    // 4. Heading hierarchy
    const headingLevels = headings.map(h => parseInt(String(h.data.level || 'h2').replace('h', '')));
    let hierarchyOk = true;
    for (let i = 1; i < headingLevels.length; i++) {
      if (headingLevels[i] > headingLevels[i - 1] + 1) {
        hierarchyOk = false;
        break;
      }
    }
    if (headingLevels.length > 1 && !hierarchyOk) {
      results.push({ id: 'heading-skip', label: 'Heading Hierarchy', status: 'warning', message: 'Heading levels are skipped (e.g., H2 → H4). Use sequential levels for accessibility.' });
    } else if (headingLevels.length > 1) {
      results.push({ id: 'heading-ok', label: 'Heading Hierarchy', status: 'pass', message: 'Heading levels follow proper hierarchy.' });
    }

    // 5. Images alt text
    const images = findBlocks(blocks, 'image');
    const imagesWithoutAlt = images.filter(img => !img.data.alt && !img.data.alt_text);
    if (images.length === 0) {
      results.push({ id: 'img-none', label: 'Images', status: 'info', message: 'No images on this page. Consider adding visual content.' });
    } else if (imagesWithoutAlt.length > 0) {
      results.push({ id: 'img-alt', label: 'Image Alt Text', status: 'error', message: `${imagesWithoutAlt.length} of ${images.length} images missing alt text. Alt text is critical for SEO and accessibility.` });
    } else {
      results.push({ id: 'img-alt-ok', label: 'Image Alt Text', status: 'pass', message: `All ${images.length} images have alt text.` });
    }

    // 6. Content length
    if (wordCount < 100) {
      results.push({ id: 'content-thin', label: 'Content Length', status: 'error', message: `Only ${wordCount} words. Pages with less than 300 words rarely rank well. Aim for 300+.` });
    } else if (wordCount < 300) {
      results.push({ id: 'content-short', label: 'Content Length', status: 'warning', message: `${wordCount} words. Consider adding more content (300+ words recommended for SEO).` });
    } else {
      results.push({ id: 'content-ok', label: 'Content Length', status: 'pass', message: `${wordCount} words — good content length.` });
    }

    // 7. Internal/external links
    const allText = allBlocks.map(b => String(b.data.content || '')).join(' ');
    const linkCount = (allText.match(/<a\s/gi) || []).length;
    const buttons = findBlocks(blocks, 'button');
    const totalLinks = linkCount + buttons.length;
    if (totalLinks === 0) {
      results.push({ id: 'links-none', label: 'Links', status: 'warning', message: 'No links found. Internal and external links help SEO.' });
    } else {
      results.push({ id: 'links-ok', label: 'Links', status: 'pass', message: `${totalLinks} links found on page.` });
    }

    // 8. URL/slug
    if (slug) {
      if (slug.length > 60) {
        results.push({ id: 'slug-long', label: 'URL Slug', status: 'warning', message: `Slug "${slug}" is long (${slug.length} chars). Keep URLs short and descriptive.` });
      } else if (/[A-Z]/.test(slug)) {
        results.push({ id: 'slug-case', label: 'URL Slug', status: 'warning', message: 'Slug contains uppercase letters. Use lowercase for consistency.' });
      } else {
        results.push({ id: 'slug-ok', label: 'URL Slug', status: 'pass', message: `URL slug "${slug}" looks good.` });
      }
    }

    // 9. Video/media
    const videos = findBlocks(blocks, 'video');
    if (videos.length > 0) {
      results.push({ id: 'video-present', label: 'Video Content', status: 'pass', message: `${videos.length} video(s) found. Video content boosts engagement.` });
    }

    // 10. Structured headings with keywords
    if (totalH1s === 1 && effectiveTitle) {
      const h1Text = h1s.length === 1
        ? String(h1s[0].data.text || '').toLowerCase()
        : String(heroBlocks[0].data.title || '').toLowerCase();
      const titleWords = effectiveTitle.toLowerCase().split(/\s+/).filter(w => w.length > 3);
      const matchingWords = titleWords.filter(w => h1Text.includes(w));
      if (matchingWords.length === 0 && titleWords.length > 0) {
        results.push({ id: 'h1-title-match', label: 'H1 & Title Match', status: 'info', message: 'H1 heading and page title share no keywords. Consider aligning them.' });
      }
    }

    return results;
  }, [blocks, pageTitle, seoTitle, seoDescription, slug]);

  const errorCount = checks.filter(c => c.status === 'error').length;
  const warningCount = checks.filter(c => c.status === 'warning').length;
  const passCount = checks.filter(c => c.status === 'pass').length;

  const overallScore = Math.round(
    (passCount / Math.max(checks.length, 1)) * 100
  );

  const scoreColor = overallScore >= 80 ? 'text-green-600' : overallScore >= 50 ? 'text-amber-600' : 'text-red-600';
  const scoreBg = overallScore >= 80 ? 'bg-green-50 border-green-200' : overallScore >= 50 ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200';

  const StatusIcon = ({ status }: { status: string }) => {
    switch (status) {
      case 'pass': return <CheckCircle size={14} className="text-green-500 shrink-0" />;
      case 'warning': return <AlertTriangle size={14} className="text-amber-500 shrink-0" />;
      case 'error': return <XCircle size={14} className="text-red-500 shrink-0" />;
      default: return <Info size={14} className="text-blue-400 shrink-0" />;
    }
  };

  return (
    <div className="p-3 space-y-4">
      {/* Score summary */}
      <div className={`rounded-lg border p-3 ${scoreBg}`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Search size={16} className={scoreColor} />
            <span className={`text-lg font-bold ${scoreColor}`}>{overallScore}%</span>
          </div>
          <div className="flex items-center gap-3 text-xs">
            <span className="text-red-600">{errorCount} errors</span>
            <span className="text-amber-600">{warningCount} warnings</span>
            <span className="text-green-600">{passCount} passed</span>
          </div>
        </div>
        <p className="text-[10px] text-gray-500 mt-1">
          {overallScore >= 80 ? 'Great! Your page is well optimized.' :
           overallScore >= 50 ? 'Decent, but there are issues to fix.' :
           'Needs work. Fix the errors below for better rankings.'}
        </p>
      </div>

      {/* Errors first */}
      {errorCount > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold uppercase tracking-wider text-red-500 mb-2">Errors</h4>
          <div className="space-y-2">
            {checks.filter(c => c.status === 'error').map(check => (
              <div key={check.id} className="flex gap-2 p-2 rounded-md bg-red-50 border border-red-100">
                <StatusIcon status={check.status} />
                <div>
                  <p className="text-xs font-medium text-gray-800">{check.label}</p>
                  <p className="text-[11px] text-gray-600 mt-0.5">{check.message}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Warnings */}
      {warningCount > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold uppercase tracking-wider text-amber-500 mb-2">Warnings</h4>
          <div className="space-y-2">
            {checks.filter(c => c.status === 'warning').map(check => (
              <div key={check.id} className="flex gap-2 p-2 rounded-md bg-amber-50 border border-amber-100">
                <StatusIcon status={check.status} />
                <div>
                  <p className="text-xs font-medium text-gray-800">{check.label}</p>
                  <p className="text-[11px] text-gray-600 mt-0.5">{check.message}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Info */}
      {checks.filter(c => c.status === 'info').length > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold uppercase tracking-wider text-blue-400 mb-2">Suggestions</h4>
          <div className="space-y-2">
            {checks.filter(c => c.status === 'info').map(check => (
              <div key={check.id} className="flex gap-2 p-2 rounded-md bg-blue-50 border border-blue-100">
                <StatusIcon status={check.status} />
                <div>
                  <p className="text-xs font-medium text-gray-800">{check.label}</p>
                  <p className="text-[11px] text-gray-600 mt-0.5">{check.message}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Passed */}
      {passCount > 0 && (
        <div>
          <h4 className="text-[10px] font-semibold uppercase tracking-wider text-green-500 mb-2">Passed ({passCount})</h4>
          <div className="space-y-1">
            {checks.filter(c => c.status === 'pass').map(check => (
              <div key={check.id} className="flex items-center gap-2 px-2 py-1.5 text-[11px] text-gray-600">
                <StatusIcon status={check.status} />
                <span>{check.label}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
