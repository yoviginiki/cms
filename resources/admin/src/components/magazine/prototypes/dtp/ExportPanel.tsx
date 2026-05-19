/**
 * M9 DTP Canvas Prototype — Export Readiness Panel
 *
 * Shows document summary, preflight status, and fake export package.
 * Prototype-only — no real export, no API calls, no file generation.
 */
import { FileText, ImageIcon, Layout, CheckCircle, AlertTriangle, XCircle, Download, Globe, FileOutput } from 'lucide-react';
import type { DtpDocument } from './mockDocument';
import type { PreflightResult } from './preflight';

interface Props {
  document: DtpDocument;
  preflight: PreflightResult;
}

const STATUS_CONFIG = {
  pass: { icon: <CheckCircle size={18} />, label: 'Ready to Export', color: 'text-green-400', bg: 'bg-green-500/10' },
  warnings: { icon: <AlertTriangle size={18} />, label: 'Export with Warnings', color: 'text-amber-400', bg: 'bg-amber-500/10' },
  blocked: { icon: <XCircle size={18} />, label: 'Not Ready — Fix Errors', color: 'text-red-400', bg: 'bg-red-500/10' },
};

export function ExportPanel({ document: doc, preflight }: Props) {
  const status = STATUS_CONFIG[preflight.status];
  const totalPages = doc.spreads.reduce((s, sp) => s + sp.pages.length, 0);
  const totalFrames = doc.spreads.reduce((s, sp) => s + sp.frames.length, 0);
  const imageFrames = doc.spreads.reduce((s, sp) => s + sp.frames.filter(f => f.type === 'image').length, 0);
  const textFrames = doc.spreads.reduce((s, sp) => s + sp.frames.filter(f => f.type === 'text' || f.type === 'quote').length, 0);
  const imagesWithSrc = doc.spreads.reduce((s, sp) => s + sp.frames.filter(f => f.type === 'image' && f.image?.src).length, 0);
  const masterObjects = doc.spreads.reduce((s, sp) => s + sp.frames.filter(f => f.isMasterObject).length, 0);

  return (
    <div className="p-3 space-y-4">
      {/* Status */}
      <div className={`flex items-center gap-3 p-4 rounded-lg ${status.bg}`}>
        <div className={status.color}>{status.icon}</div>
        <div>
          <span className={`text-sm font-semibold ${status.color}`}>{status.label}</span>
          <div className="text-[10px] text-neutral-400 mt-0.5">Score: {preflight.score}/100</div>
        </div>
      </div>

      {/* Document Summary */}
      <div>
        <h4 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Document</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3 space-y-1">
          <Row icon={<Layout size={11} />} label="Title" value={doc.title} />
          <Row icon={<Layout size={11} />} label="Spreads" value={doc.spreads.length} />
          <Row icon={<FileText size={11} />} label="Pages" value={totalPages} />
          <Row icon={<FileText size={11} />} label="Total frames" value={totalFrames} />
          <Row icon={<FileText size={11} />} label="Text frames" value={textFrames} />
          <Row icon={<ImageIcon size={11} />} label="Image frames" value={imageFrames} />
          <Row icon={<ImageIcon size={11} />} label="Images placed" value={`${imagesWithSrc}/${imageFrames}`} />
          <Row icon={<Layout size={11} />} label="Master objects" value={masterObjects} />
        </div>
      </div>

      {/* Preflight Summary */}
      <div>
        <h4 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Preflight</h4>
        <div className="bg-neutral-700/50 rounded-lg p-3 space-y-1">
          {preflight.errors.length > 0 && (
            <div className="flex items-center gap-2 text-[10px] text-red-400">
              <XCircle size={11} /> {preflight.errors.length} error{preflight.errors.length !== 1 ? 's' : ''}
            </div>
          )}
          {preflight.warnings.length > 0 && (
            <div className="flex items-center gap-2 text-[10px] text-amber-400">
              <AlertTriangle size={11} /> {preflight.warnings.length} warning{preflight.warnings.length !== 1 ? 's' : ''}
            </div>
          )}
          {preflight.info.length > 0 && (
            <div className="flex items-center gap-2 text-[10px] text-blue-400">
              <FileText size={11} /> {preflight.info.length} info
            </div>
          )}
          {preflight.issues.length === 0 && (
            <div className="flex items-center gap-2 text-[10px] text-green-400">
              <CheckCircle size={11} /> All checks passed
            </div>
          )}
        </div>
      </div>

      {/* Export Targets (all disabled/future) */}
      <div>
        <h4 className="text-[10px] font-semibold text-neutral-300 uppercase tracking-wider mb-2">Export Targets</h4>
        <div className="space-y-1.5">
          <ExportTarget icon={<Globe size={13} />} label="HTML / Flipbook" status="prototype" desc="Web magazine viewer" />
          <ExportTarget icon={<FileOutput size={13} />} label="PDF" status="future" desc="Print-ready PDF/X output" />
          <ExportTarget icon={<Download size={13} />} label="Asset Package" status="future" desc="ZIP with images + metadata" />
        </div>
      </div>

      {/* Fake Export Button */}
      <div className="border-t border-neutral-700 pt-3">
        <button
          disabled={preflight.status === 'blocked'}
          onClick={() => alert(`Export Summary (Prototype)\n\nTitle: ${doc.title}\nPages: ${totalPages}\nFrames: ${totalFrames}\nImages: ${imagesWithSrc}/${imageFrames}\nPreflight: ${preflight.status.toUpperCase()}\nScore: ${preflight.score}/100\n\nThis is a prototype — no files are generated.`)}
          className="btn btn-sm w-full gap-1 text-[11px] bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-30 disabled:cursor-not-allowed"
        >
          <Download size={13} />
          {preflight.status === 'blocked' ? 'Fix errors before export' : 'Prepare Export Summary'}
        </button>
        <p className="text-[8px] text-neutral-500 text-center mt-1">Prototype only — no files generated</p>
      </div>
    </div>
  );
}

function Row({ icon, label, value }: { icon: React.ReactNode; label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between py-0.5">
      <div className="flex items-center gap-1.5 text-[10px] text-neutral-400">{icon}{label}</div>
      <span className="text-[11px] font-mono text-neutral-200">{value}</span>
    </div>
  );
}

function ExportTarget({ icon, label, status, desc }: { icon: React.ReactNode; label: string; status: 'prototype' | 'future'; desc: string }) {
  return (
    <div className={`flex items-center gap-2 px-2.5 py-2 rounded-lg border ${status === 'prototype' ? 'border-blue-500/30 bg-blue-500/5' : 'border-neutral-600/30 bg-neutral-700/20 opacity-50'}`}>
      <div className="text-neutral-400">{icon}</div>
      <div className="flex-1">
        <div className="flex items-center gap-1.5">
          <span className="text-[10px] text-neutral-200 font-medium">{label}</span>
          <span className={`text-[7px] px-1 rounded ${status === 'prototype' ? 'bg-blue-500/20 text-blue-300' : 'bg-neutral-600 text-neutral-400'}`}>
            {status === 'prototype' ? 'Prototype' : 'Future'}
          </span>
        </div>
        <p className="text-[9px] text-neutral-500">{desc}</p>
      </div>
    </div>
  );
}
