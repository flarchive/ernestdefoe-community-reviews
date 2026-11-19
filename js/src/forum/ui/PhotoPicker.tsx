import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type { ReviewPhoto } from '../models/Review';
import { setting, t, tx } from '../util';

interface Attrs extends ComponentAttrs {
  photos: ReviewPhoto[];
  onchange: (photos: ReviewPhoto[]) => void;
}

interface Uploaded {
  data: { id: string; attributes: { url: string; thumbUrl: string; width: number; height: number } };
}

/**
 * MyBB's review photos: up to the admin's maximum, the first one is the thumbnail ("Set as thumbnail" moves a
 * photo first). Files go to the forum (re-encoded there), or, when the admin picked imgur, straight from the
 * browser to imgur with the admin's Client ID (MyBB's way), and the forum keeps the link.
 */
export default class PhotoPicker extends Component<Attrs> {
  private uploading = 0;

  view() {
    const { photos } = this.attrs;
    const max = Number(setting('max_photos'));
    return (
      <div className="Form-group">
        <span className="CR-label">
          {t('composer.photos')}
          {setting('require_photo') ? <span className="CR-muted"> {t('composer.required')}</span> : null}
          <span className="CR-counter">{t('composer.photo_count', { count: photos.length, max })}</span>
        </span>
        <div className="CR-drop">
          {photos.map((photo, index) => this.tile(photo, index))}
          {photos.length + this.uploading < max ? (
            <label className="CR-addphoto">
              <i className={this.uploading ? 'fas fa-spinner fa-spin' : 'fas fa-cloud-arrow-up'} aria-hidden="true" /> {t('composer.add_photos')}
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                multiple
                className="CR-visually-hidden"
                onchange={(e: Event) => this.pick(e.target as HTMLInputElement)}
              />
            </label>
          ) : null}
        </div>
      </div>
    );
  }

  /** One picked photo: the first is the thumbnail (MyBB "Review thumbnail"); any other can be made first. */
  private tile(photo: ReviewPhoto, index: number): Mithril.Children {
    const { photos } = this.attrs;
    return (
      <span className={classList('CR-pick', index === 0 && 'is-thumb')} key={photo.id}>
        <img src={photo.thumbUrl} alt="" />
        {index === 0 ? (
          <span className="CR-pick-tag">
            <i className="fas fa-star" aria-hidden="true" /> {t('composer.thumbnail')}
          </span>
        ) : (
          <button
            type="button"
            className="CR-pick-tag CR-pick-tag--ghost"
            onclick={() => this.attrs.onchange([photo, ...photos.filter((p) => p !== photo)])}
          >
            {t('composer.set_thumbnail')}
          </button>
        )}
        <Button
          className="Button Button--icon CR-pick-remove"
          icon="fas fa-xmark"
          aria-label={tx('composer.remove_photo')}
          onclick={() => this.attrs.onchange(photos.filter((p) => p !== photo))}
        />
      </span>
    );
  }

  private pick(input: HTMLInputElement): void {
    const max = Number(setting('max_photos'));
    const files = Array.from(input.files || []).slice(0, Math.max(0, max - this.attrs.photos.length - this.uploading));
    input.value = '';
    files.forEach((file) => {
      this.uploading++;
      this.upload(file)
        .then((photo) => this.attrs.onchange([...this.attrs.photos, photo]))
        .catch(() => {
          /* app.request already showed the error; imgur failures show their own */
        })
        .finally(() => {
          this.uploading--;
          m.redraw();
        });
    });
  }

  private upload(file: File): Promise<ReviewPhoto> {
    const api = `${app.forum.attribute('apiUrl')}/review-photos`;
    const clientId = setting<string | null>('imgur_client_id');
    const toPhoto = (r: Uploaded): ReviewPhoto => ({ id: Number(r.data.id), ...r.data.attributes });

    if (clientId) {
      // Straight to imgur, like MyBB: a plain fetch on purpose. app.request would add this forum's CSRF token
      // and session headers to a request for another site. Only the resulting i.imgur.com link comes back here.
      const form = new FormData();
      form.append('image', file);
      return fetch('https://api.imgur.com/3/image', { method: 'POST', headers: { Authorization: `Client-ID ${clientId}` }, body: form })
        .then((r) => r.json())
        .then((json: { data?: { link?: string } }) => {
          if (!json.data?.link) throw new Error('imgur');
          return app.request<Uploaded>({ method: 'POST', url: api, body: { imgurUrl: json.data.link } }).then(toPhoto);
        })
        .catch((error) => {
          if (error instanceof Error && error.message === 'imgur') app.alerts.show({ type: 'error' }, tx('composer.imgur_failed'));
          throw error;
        });
    }

    const body = new FormData();
    body.append('file', file);
    return app.request<Uploaded>({ method: 'POST', url: api, serialize: (raw: FormData) => raw, body }).then(toPhoto);
  }
}
