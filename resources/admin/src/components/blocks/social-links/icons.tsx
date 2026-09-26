import { Facebook, Instagram, X, Linkedin, Youtube, Music2, Github, MessageCircle, MessageSquare, Send, Mail, Phone, Globe, type LucideIcon } from 'lucide-react';

/** Same keys and shapes as App\\Support\\Blocks\\SocialIcons (server render). */
export const SOCIAL_ICONS: Record<string, LucideIcon> = {
  facebook: Facebook, instagram: Instagram, x: X, linkedin: Linkedin, youtube: Youtube, tiktok: Music2,
  github: Github, whatsapp: MessageCircle, telegram: Send, contact: MessageSquare, email: Mail, phone: Phone, website: Globe,
};
