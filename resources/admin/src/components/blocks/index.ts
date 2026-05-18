/**
 * Block Registry Entry Point
 *
 * Every block type must be imported here to register with the global blockRegistry.
 * To add a new block type:
 *   1. Create a folder: components/blocks/my-block/
 *   2. Add definition.ts, Preview.tsx, Editor.tsx, index.ts
 *   3. Add an import line below
 *
 * The index.ts in each block folder calls blockRegistry.register() on import.
 */

import './hero';
import './text';
import './image';
import './column';
import './columns';
import './heading';
import './button';
import './row';
import './section';
import './spacer';
import './video';
import './html-embed';
import './tabs';
import './accordion';
import './code';
import './contact-form';
import './rich-text';
import './paragraph';
import './dropcap';
import './pullquote';
import './caption';
import './runningtext';
import './footnote';
import './sidenote';
import './textdivider';
import './list';
import './imagecaption';
import './fullbleed';
import './gallery';
import './beforeafter';
import './audio';
import './icon';
import './logostrip';
import './container';
import './grid';
import './divider';
import './stickysidebar';
import './overlap';
import './group';
import './ctabanner';
import './modal';
import './tooltip';
import './toc';
import './readingprogress';
import './customform';
import './pricingcard';
import './featurecomparison';
import './paywall';
import './socialembed';
import './table';
import './pricingtable';
import './featuregrid';
import './testimonial';
import './timeline';
import './stats';
import './map';
import './chart';
import './postcard';
import './postgrid';
import './latestposts';
import './categorylist';
import './authorbox';
import './relatedposts';
import './newsletter';
import './sharebuttons';
import './flipbook';
import './scroll_page';
import './menu';
import './breadcrumbs';
import './anchormenu';
import './post-title';
import './post-content';
import './post-image';
import './post-video';
import './post-meta';
import './post-excerpt';
import './post-navigation';
import './post-loop';
import './category-header';
import './archive-pagination';

export { blockRegistry } from './registry';
