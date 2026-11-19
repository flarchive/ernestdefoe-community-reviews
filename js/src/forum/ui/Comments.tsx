import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import { one } from './bits';
import type Item from '../models/Item';
import type Comment from '../models/Comment';
import type Review from '../models/Review';
import { failed, setting, t, tx } from '../util';
import Menu, { type MenuEntry } from './Menu';
import { openReport } from './open';

export interface CommentsAttrs extends ComponentAttrs {
  item: Item;
  /** Set by "Reply" on a review: the next comment is "in reply to" it. */
  replyTo: Review | null;
  onclearreply: () => void;
  onchange: () => void;
}

/** MyBB's product comments: under the reviews, in the admin's display order, a comment can answer a review. */
export default class Comments extends Component<CommentsAttrs> {
  private comments: Comment[] = [];
  private loading = true;
  private more = false;
  private draft = '';
  private sending = false;
  private editing: Comment | null = null;
  private editDraft = '';

  oninit(vnode: Mithril.Vnode<CommentsAttrs, this>) {
    super.oninit(vnode);
    this.load(0);
  }

  load(offset: number): void {
    this.loading = true;
    app.store
      .find<Comment[]>('review-comments', {
        filter: { item: this.attrs.item.id()! },
        page: { offset, limit: Number(setting('comments_per_page')), total: 0 },
      })
      .then((list) => {
        this.comments = offset ? [...this.comments, ...list] : [...list];
        this.more = !!list.payload?.links?.next;
      })
      .catch(failed)
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    const { item, replyTo } = this.attrs;
    return (
      <section className="CR-comments" id="comments">
        <div className="CR-sectionhead">
          <h2>{t('comments.title')}</h2>
          <span className="CR-muted">{item.commentCount()}</span>
        </div>
        {item.canComment() ? this.form(replyTo) : null}
        {this.comments.map((comment) => this.comment(comment))}
        {!this.comments.length && !this.loading ? <p className="CR-empty">{t('comments.empty')}</p> : null}
        {this.loading ? <LoadingIndicator /> : null}
        {this.more && !this.loading ? (
          <Button className="Button Button--block CR-more" onclick={() => this.load(this.comments.length)}>
            {t('comments.load_more')}
          </Button>
        ) : null}
      </section>
    );
  }

  private form(replyTo: Review | null): Mithril.Children {
    const max = Number(setting('comment_max'));
    return (
      <form
        className="CR-comment-form"
        onsubmit={(e: SubmitEvent) => {
          e.preventDefault();
          this.send();
        }}
      >
        {replyTo ? (
          <div className="CR-replyto">
            <i className="fas fa-reply" aria-hidden="true" />{' '}
            {t('comments.replying_to', { name: one(replyTo.user())?.displayName() || '', id: replyTo.id() })}
            <Button
              className="Button Button--link"
              icon="fas fa-xmark"
              aria-label={tx('comments.cancel_reply')}
              onclick={() => this.attrs.onclearreply()}
            />
          </div>
        ) : null}
        <textarea
          className="FormControl"
          rows={3}
          maxlength={max}
          aria-label={tx('comments.label')}
          placeholder={tx('comments.placeholder')}
          value={this.draft}
          oninput={(e: InputEvent) => (this.draft = (e.target as HTMLTextAreaElement).value)}
        />
        <div className="CR-comment-actions">
          <span className="CR-muted">{t('comments.counter', { count: this.draft.length, max })}</span>
          <Button type="submit" className="Button Button--primary" loading={this.sending} disabled={!this.draft.trim()}>
            {t('comments.add')}
          </Button>
        </div>
      </form>
    );
  }

  private send(): void {
    const body = this.draft.trim();
    if (!body) return;
    this.sending = true;
    const record = app.store.createRecord<Comment>('review-comments');
    record
      .save({ body, reviewId: this.attrs.replyTo ? Number(this.attrs.replyTo.id()) : null, relationships: { item: this.attrs.item } } as never)
      .then(() => {
        this.draft = '';
        this.attrs.onclearreply();
        this.attrs.onchange();
        this.load(0);
      })
      .catch(() => {
        /* app.request already showed the error */
      })
      .finally(() => {
        this.sending = false;
        m.redraw();
      });
  }

  private entries(comment: Comment): MenuEntry[] {
    const entries: MenuEntry[] = [];
    if (comment.canEdit())
      entries.push({ icon: 'fas fa-pen', label: t('comments.edit'), run: () => ((this.editing = comment), (this.editDraft = comment.body() || '')) });
    if (comment.canDelete()) {
      entries.push({
        icon: 'far fa-trash-can',
        label: t('comments.delete'),
        run: () => {
          if (confirm(tx('comments.delete_confirm')))
            comment
              .delete()
              .then(() => (this.attrs.onchange(), this.load(0)))
              .catch(failed);
        },
      });
    }
    if (comment.canReport() && app.session.user?.id() !== (comment.user() || null)?.id()) {
      entries.push({ icon: 'far fa-flag', label: t('report.action'), run: () => openReport('comment', Number(comment.id())) });
    }
    return entries;
  }

  private comment(comment: Comment): Mithril.Children {
    const user = comment.user() || null;
    return (
      <div className={classList('CR-comment', comment.isOfficial() && 'CR-comment--official')} id={`comment${comment.id()}`} key={comment.id()}>
        <Avatar user={user} className="CR-comment-avatar" />
        <div className="CR-comment-main">
          <div className="CR-comment-head">
            {user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(null)}
            {comment.isOfficial() ? (
              <span className="CR-badge">
                <i className="fas fa-shield-halved" aria-hidden="true" /> {t('comments.official')}
              </span>
            ) : null}
            <span className="CR-muted">
              · {humanTime(comment.createdAt())}
              {comment.reviewId()
                ? [
                    ' · ',
                    <a href={`#review${comment.reviewId()}`}>
                      {t('comments.in_reply_to', { name: comment.replyToName() || '', id: comment.reviewId() })}
                    </a>,
                  ]
                : null}
            </span>
            <span className="CR-grow" />
            <Menu entries={this.entries(comment)} label={tx('comments.controls')} />
          </div>
          {this.editing === comment ? this.editor(comment) : <div className="CR-comment-body">{m.trust(comment.bodyHtml())}</div>}
        </div>
      </div>
    );
  }

  private editor(comment: Comment): Mithril.Children {
    return (
      <form
        className="CR-comment-form"
        onsubmit={(e: SubmitEvent) => {
          e.preventDefault();
          comment
            .save({ body: this.editDraft })
            .then(() => (this.editing = null))
            .catch(() => {
              /* app.request already showed the error */
            })
            .finally(() => m.redraw());
        }}
      >
        <textarea
          className="FormControl"
          rows={3}
          aria-label={tx('comments.label')}
          value={this.editDraft}
          oninput={(e: InputEvent) => (this.editDraft = (e.target as HTMLTextAreaElement).value)}
        />
        <div className="CR-comment-actions">
          <Button className="Button" onclick={() => (this.editing = null)}>
            {t('comments.cancel')}
          </Button>
          <Button type="submit" className="Button Button--primary">
            {t('comments.save')}
          </Button>
        </div>
      </form>
    );
  }
}
