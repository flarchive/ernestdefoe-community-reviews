/*
 * The one lazy chunk: the pages, the composer and the modals. One entry on purpose: two chunks sharing a module
 * would make webpack emit a numbered shared chunk that jsDirectory doesn't publish.
 */
import type Mithril from 'mithril';
import type UserPageComponent from 'flarum/forum/components/UserPage';
import type { IUserPageAttrs } from 'flarum/forum/components/UserPage';
import { defaultExport, runtime } from '../../common/runtime';
import UserReviews from './UserReviews';

export { loadCore } from './core';
export { default as CatalogPage } from './CatalogPage';
export { default as ItemPage } from './ItemPage';
export { default as ReportsPage } from './ReportsPage';
export { openComposer, openAddItem } from './open';

let made: unknown = null;

/** The profile tab: a UserPage built from core's lazily loaded UserPage module. */
export async function userReviewsPage(): Promise<unknown> {
  if (made) return made;
  const UserPage = defaultExport<typeof UserPageComponent>(await runtime()!.reg.asyncModuleImport('flarum/forum/components/UserPage'));

  made = class UserReviewsPage extends UserPage {
    oninit(vnode: Mithril.Vnode<IUserPageAttrs, this>) {
      super.oninit(vnode);
      this.loadUser(m.route.param('username'));
    }

    content() {
      return this.user ? m(UserReviews, { key: this.user.id(), user: this.user }) : null;
    }
  };

  return made;
}
