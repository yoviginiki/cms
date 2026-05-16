import type { LucideIcon } from 'lucide-react';
import {
  ALargeSmall, AlignLeft, Anchor, Asterisk,
  BarChart2, BarChart3, BookOpen,
  ChevronRight, ChevronsUpDown, ClipboardList, Clock, Code, CodeXml, Columns, Columns2, CreditCard,
  Expand, FileEdit, FileText, FolderTree,
  GalleryHorizontalEnd, Globe, Group,
  Heading, Image, ImagePlus,
  Layers, Layout, LayoutGrid, Link, List, ListTree, Lock,
  Mail, MapPin, Maximize2, Megaphone, Menu, MessageCircle, MessageSquareQuote, Minus, MousePointer, MoveVertical, Music,
  Newspaper,
  PanelLeft, PanelRight, PanelTop, PanelTopDashed,
  Quote, RectangleVertical, Rows3,
  Share2, Smile, SplitSquareHorizontal, Square, Subtitles,
  Table, Table2, TrendingUp, Type,
  UserCircle, Video,
  Box,
} from 'lucide-react';

const ICON_MAP: Record<string, LucideIcon> = {
  ALargeSmall, AlignLeft, Anchor, Asterisk,
  BarChart2, BarChart3, BookOpen, Box,
  ChevronRight, ChevronsUpDown, ClipboardList, Clock, Code, CodeXml, Columns, Columns2, CreditCard,
  Expand, FileEdit, FileText, FolderTree,
  GalleryHorizontalEnd, Globe, Group,
  Heading, Image, ImagePlus,
  Layers, Layout, LayoutGrid, Link, List, ListTree, Lock,
  Mail, MapPin, Maximize2, Megaphone, Menu, MessageCircle, MessageSquareQuote, Minus, MousePointer, MoveVertical, Music,
  Newspaper,
  PanelLeft, PanelRight, PanelTop, PanelTopDashed,
  Quote, RectangleVertical, Rows3,
  Share2, Smile, SplitSquareHorizontal, Square, Subtitles,
  Table, Table2, TrendingUp, Type,
  UserCircle, Video,
};

interface BlockIconProps {
  icon: string;
  size?: number;
  className?: string;
}

export function BlockIcon({ icon, size = 16, className = '' }: BlockIconProps) {
  const LucideComponent = ICON_MAP[icon];

  if (LucideComponent) {
    return <LucideComponent size={size} className={className} />;
  }

  // Fallback for emoji/text icons
  return (
    <span className={className} style={{ fontSize: size * 0.75, lineHeight: 1 }}>
      {icon}
    </span>
  );
}
