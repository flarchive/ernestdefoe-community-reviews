import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import classList from 'flarum/common/utils/classList';
import CategoriesTab from './CategoriesTab';
import SettingsTab from './SettingsTab';
import MergeTab from './MergeTab';
import { t } from './util';

type Tab = 'categories' | 'settings' | 'merge';

/**
 * The extension's admin page: Categories (with their fields to rate and product details), Settings (every MyBB
 * option) and Merge. The permission grid below it is core's (ExtensionPage keeps it).
 */
export default class ReviewsPage extends ExtensionPage {
  private tab: Tab = 'categories';

  content() {
    return (
      <div className="ExtensionPage-settings CRAdmin">
        <div className="container">
          <nav className="CRAdmin-tabs" role="tablist">
            {(['categories', 'settings', 'merge'] as Tab[]).map((tab) => (
              <button
                type="button"
                role="tab"
                aria-selected={this.tab === tab ? 'true' : 'false'}
                className={classList('CRAdmin-tab', this.tab === tab && 'is-active')}
                onclick={() => (this.tab = tab)}
              >
                {t(`tabs.${tab}`)}
              </button>
            ))}
          </nav>
          {this.tab === 'categories' ? <CategoriesTab /> : this.tab === 'settings' ? <SettingsTab /> : <MergeTab />}
        </div>
      </div>
    );
  }
}
