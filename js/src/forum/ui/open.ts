import app from 'flarum/forum/app';
import type Category from '../models/Category';
import type Item from '../models/Item';
import type { ReviewPhoto } from '../models/Review';
import Composer, { type ComposerAttrs } from './Composer';
import { Lightbox, MergeModal, ReportModal } from './modals';

export function openComposer(attrs: Pick<ComposerAttrs, 'item' | 'review' | 'editItem' | 'category' | 'onsaved'>): void {
  app.modal.show(Composer as never, attrs as never);
}

export function openAddItem(category: Category | null): void {
  if (!app.session.user) {
    app.modal.show(() => import('flarum/forum/components/LogInModal'));
    return;
  }
  app.modal.show(Composer as never, { category } as never);
}

export function openReport(subjectType: 'item' | 'review' | 'comment', subjectId: number): void {
  app.modal.show(ReportModal as never, { subjectType, subjectId } as never);
}

export function openMerge(item: Item): void {
  app.modal.show(MergeModal as never, { item } as never);
}

export function openLightbox(photos: ReviewPhoto[], index: number): void {
  app.modal.show(Lightbox as never, { photos, index } as never);
}
