import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';

export interface MenuEntry {
  icon: string;
  label: Mithril.Children;
  run: () => unknown;
  active?: boolean;
}

interface Attrs {
  entries: MenuEntry[];
  label: string;
  /** The toggle's content; the ⋯ icon when left out. */
  toggle?: Mithril.Children;
  toggleClassName?: string;
  right?: boolean;
}

/**
 * A small menu button (the ⋯ on a review, the sort picker). Not core's Dropdown: that one closes through
 * Bootstrap and, where the browser has it, a CloseWatcher, and in Safari the menu closed before a click on
 * an item landed (found in Testimonials 1.0.2). Here an item runs on its own click, then the menu closes.
 * Keys: arrows, Home and End move through the items; Escape closes back to the button; Tab closes.
 */
export default class Menu extends Component<Attrs> {
  open = false;
  private focusNext: 'first' | 'last' | null = null;
  private menuId = `CRMenu-${Math.random().toString(36).slice(2, 9)}`;
  private outside = (e: Event) => {
    if (this.open && this.element && !this.element.contains(e.target as Node)) this.close();
  };
  // Safari doesn't focus a button on click, so after a mouse click Escape goes to the page, not here.
  private escape = (e: KeyboardEvent) => {
    if (this.open && e.key === 'Escape' && !e.defaultPrevented) {
      e.preventDefault();
      this.close();
    }
  };

  oncreate(vnode: Mithril.VnodeDOM<Attrs, this>) {
    super.oncreate(vnode);
    document.addEventListener('pointerdown', this.outside, true);
    document.addEventListener('keydown', this.escape);
  }

  onupdate(vnode: Mithril.VnodeDOM<Attrs, this>) {
    super.onupdate(vnode);
    if (this.open && this.focusNext) {
      const items = this.items();
      (this.focusNext === 'first' ? items[0] : items[items.length - 1])?.focus();
      this.focusNext = null;
    }
  }

  onremove(vnode: Mithril.VnodeDOM<Attrs, this>) {
    super.onremove(vnode);
    document.removeEventListener('pointerdown', this.outside, true);
    document.removeEventListener('keydown', this.escape);
  }

  private items(): HTMLElement[] {
    return Array.from(this.element?.querySelectorAll<HTMLElement>('[role="menuitem"]') || []);
  }

  private toggleButton(): HTMLElement | null {
    return this.element?.querySelector<HTMLElement>('.CR-menu-toggle') || null;
  }

  close(): void {
    this.open = false;
    m.redraw();
  }

  private onkeydown(e: KeyboardEvent): void {
    if (!this.open) {
      if (e.target === this.toggleButton() && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        e.preventDefault();
        this.open = true;
        this.focusNext = e.key === 'ArrowDown' ? 'first' : 'last';
        m.redraw();
      }
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      this.close();
      this.toggleButton()?.focus();
      return;
    }
    if (e.key === 'Tab') {
      this.close();
      return;
    }
    const items = this.items();
    const at = items.indexOf(document.activeElement as HTMLElement);
    let next = -1;
    if (e.key === 'ArrowDown') next = at < 0 ? 0 : (at + 1) % items.length;
    else if (e.key === 'ArrowUp') next = at < 0 ? items.length - 1 : (at - 1 + items.length) % items.length;
    else if (e.key === 'Home') next = 0;
    else if (e.key === 'End') next = items.length - 1;
    if (next >= 0 && items[next]) {
      e.preventDefault();
      items[next].focus();
    }
  }

  private run(fn: () => unknown): void {
    this.open = false;
    this.toggleButton()?.focus();
    fn();
    m.redraw();
  }

  view() {
    const { entries, label } = this.attrs;
    if (!entries.length) return null;
    return (
      <div className={classList('ButtonGroup Dropdown CR-menu', this.open && 'open')} onkeydown={(e: KeyboardEvent) => this.onkeydown(e)}>
        <button
          type="button"
          className={classList('CR-menu-toggle', this.attrs.toggleClassName || 'Button Button--icon Button--flat')}
          aria-haspopup="menu"
          aria-expanded={this.open ? 'true' : 'false'}
          aria-controls={this.menuId}
          aria-label={label}
          title={label}
          onclick={(e: MouseEvent) => {
            this.open = !this.open;
            // Enter or Space (a click with no pointer) puts focus on the first item.
            if (this.open && e.detail === 0) this.focusNext = 'first';
          }}
        >
          {this.attrs.toggle ?? <Icon name="fas fa-ellipsis-h" className="Button-icon" />}
        </button>
        {this.open ? (
          <ul
            id={this.menuId}
            className={classList('Dropdown-menu CR-menu-list', this.attrs.right !== false && 'Dropdown-menu--right')}
            role="menu"
            aria-label={label}
          >
            {entries.map((entry) => (
              <li role="none" className={entry.active ? 'active' : undefined}>
                <Button className="CR-menu-item" role="menuitem" icon={entry.icon} onclick={() => this.run(entry.run)}>
                  {entry.label}
                </Button>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    );
  }
}
