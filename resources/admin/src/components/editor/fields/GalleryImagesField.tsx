import React, { useState } from 'react';
import { DndContext, closestCenter, PointerSensor, KeyboardSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, useSortable, arrayMove, rectSortingStrategy, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { X, Plus, GripVertical, Trash2 } from 'lucide-react';
import { AssetPicker, type PickedAsset } from '@/components/ui/AssetPicker';
import type { GalleryImage } from '@/components/blocks/gallery/types';

/** Stable per-item key: asset id, else the URL (duplicates get an index suffix). */
function keyOf(img: GalleryImage, i: number, all: GalleryImage[]): string {
  const base = img.id || img.src;
  const firstIdx = all.findIndex(o => (o.id || o.src) === base);
  return firstIdx === i ? base : `${base}#${i}`;
}

interface GalleryImagesFieldProps {
  images: GalleryImage[];
  onChange: (images: GalleryImage[]) => void;
  /** Hint under the thumbnails, e.g. what the per-image link does in this block. */
  linkHint?: string;
}

/**
 * Multi-image manager shared by gallery-style blocks: thumbnails with drag
 * reorder + remove, per-image alt / caption / link, "Add images" through the
 * media library in multi-select mode.
 */
export function GalleryImagesField({ images, onChange, linkHint }: GalleryImagesFieldProps) {
  const [pickerOpen, setPickerOpen] = useState(false);
  const [editing, setEditing] = useState<number | null>(null);

  const setImages = onChange;
  const patchImage = (i: number, patch: Partial<GalleryImage>) =>
    setImages(images.map((img, idx) => (idx === i ? { ...img, ...patch } : img)));
  const removeImage = (i: number) => {
    setImages(images.filter((_, idx) => idx !== i));
    setEditing(e => (e === null ? null : e === i ? null : e > i ? e - 1 : e));
  };

  const addAssets = (picked: PickedAsset[]) => {
    const existing = new Set(images.map(i => i.src));
    const added = picked
      .filter(a => !existing.has(a.url))
      .map<GalleryImage>(a => ({ id: a.id, src: a.url, alt: a.alt_text || '', caption: '', width: a.width ?? null, height: a.height ?? null }));
    setImages([...images, ...added]);
    setPickerOpen(false);
  };

  const keys = images.map((img, i) => keyOf(img, i, images));
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );
  const onDragEnd = (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    const from = keys.indexOf(String(active.id));
    const to = keys.indexOf(String(over.id));
    if (from < 0 || to < 0) return;
    setImages(arrayMove(images, from, to));
    setEditing(cur => (cur === null ? null : cur === from ? to : cur));
  };

  const current = editing !== null ? images[editing] : null;

  return (
    <div className="space-y-3">
      <div>
        <div className="flex items-center justify-between mb-1.5">
          <label className="text-[11px] text-base-content/50">Images <span className="text-base-content/30">({images.length})</span></label>
          <div className="flex items-center gap-1">
            {images.length > 0 && (
              <button type="button" onClick={() => { if (confirm('Remove all images?')) { setImages([]); setEditing(null); } }}
                className="btn btn-ghost btn-xs text-[10px] text-error gap-1"><Trash2 size={10} /> Clear</button>
            )}
            <button type="button" onClick={() => setPickerOpen(true)} className="btn btn-primary btn-xs text-[11px] gap-1">
              <Plus size={11} /> Add images
            </button>
          </div>
        </div>

        {images.length === 0 ? (
          <button type="button" onClick={() => setPickerOpen(true)}
            className="w-full rounded-lg border-2 border-dashed border-base-300/50 hover:border-primary/50 p-6 text-center text-[11px] text-base-content/40 transition-colors">
            No images yet. Click to choose or upload from the media library.
          </button>
        ) : (
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
            <SortableContext items={keys} strategy={rectSortingStrategy}>
              <div className="grid grid-cols-3 gap-1.5">
                {images.map((img, i) => (
                  <Thumb key={keys[i]} id={keys[i]} img={img} active={editing === i}
                    onClick={() => setEditing(editing === i ? null : i)} onRemove={() => removeImage(i)} />
                ))}
              </div>
            </SortableContext>
          </DndContext>
        )}
        {images.length > 1 && <p className="text-[10px] text-base-content/30 mt-1">Drag to reorder · click a thumbnail to edit its text</p>}
      </div>

      {current && editing !== null && (
        <div className="rounded-lg border border-base-300/30 bg-base-200/30 p-2.5 space-y-2">
          <div className="flex items-center gap-2">
            <img src={current.src} alt="" className="w-10 h-10 rounded object-cover shrink-0" />
            <p className="text-[10px] text-base-content/50 truncate flex-1">Image {editing + 1} of {images.length}</p>
            <button type="button" onClick={() => setEditing(null)} className="btn btn-ghost btn-xs btn-square"><X size={11} /></button>
          </div>
          <div>
            <label className="text-[10px] text-base-content/50 mb-0.5 block">Alt text</label>
            <input type="text" value={current.alt || ''} onChange={e => patchImage(editing, { alt: e.target.value })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="Describe the image" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/50 mb-0.5 block">Caption</label>
            <input type="text" value={current.caption || ''} onChange={e => patchImage(editing, { caption: e.target.value })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="Shown with the image and in the lightbox" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/50 mb-0.5 block">Link <span className="text-base-content/30">({linkHint || 'optional, replaces lightbox for this image'})</span></label>
            <input type="text" value={current.link || ''} onChange={e => patchImage(editing, { link: e.target.value })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="https://… or /page/" />
          </div>
          <button type="button" onClick={() => removeImage(editing)} className="btn btn-ghost btn-xs text-[10px] text-error gap-1 w-full">
            <Trash2 size={10} /> Remove
          </button>
        </div>
      )}

      <AssetPicker
        open={pickerOpen}
        multiple
        accept="image"
        excludeUrls={images.map(i => i.src)}
        onClose={() => setPickerOpen(false)}
        onSelect={(a) => addAssets([a])}
        onSelectMany={addAssets}
      />
    </div>
  );
}

function Thumb({ id, img, active, onClick, onRemove }: { id: string; img: GalleryImage; active: boolean; onClick: () => void; onRemove: () => void }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  const style: React.CSSProperties = { transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.5 : 1 };
  return (
    <div ref={setNodeRef} style={style}
      className={`relative group aspect-square rounded-md overflow-hidden border-2 cursor-pointer ${active ? 'border-primary ring-2 ring-primary/20' : 'border-base-300/30 hover:border-primary/40'}`}
      onClick={onClick} title={img.alt || img.caption || img.src}>
      <img src={img.src} alt={img.alt || ''} className="w-full h-full object-cover" loading="lazy" draggable={false} />
      <button type="button" {...attributes} {...listeners} onClick={e => e.stopPropagation()}
        className="absolute top-1 left-1 p-0.5 rounded bg-base-100/80 text-base-content/50 opacity-0 group-hover:opacity-100 cursor-grab" title="Drag to reorder">
        <GripVertical size={11} />
      </button>
      <button type="button" onClick={e => { e.stopPropagation(); onRemove(); }}
        className="absolute top-1 right-1 p-0.5 rounded bg-base-100/80 text-base-content/60 hover:text-error opacity-0 group-hover:opacity-100" title="Remove">
        <X size={11} />
      </button>
      {img.caption && <span className="absolute bottom-0 inset-x-0 bg-black/50 text-white text-[9px] px-1 py-0.5 truncate">{img.caption}</span>}
    </div>
  );
}
