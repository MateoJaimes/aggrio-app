import { DOCUMENT } from '@angular/common';
import { DestroyRef, Injectable, NgZone, inject } from '@angular/core';
import { fieldHelpText } from './field-help-text';

/** Adds the same accessible help to static forms and schema-generated fields. */
@Injectable({ providedIn: 'root' })
export class FieldHelp {
  private readonly document = inject(DOCUMENT);
  private readonly zone = inject(NgZone);
  private readonly destroy = inject(DestroyRef);
  private sequence = 0;

  start(root: HTMLElement) {
    const window = this.document.defaultView;
    if (!window) return;
    this.zone.runOutsideAngular(() => {
      let active: HTMLElement | null = null;
      const popup = this.document.createElement('div');
      popup.className = 'field-help-popup';
      popup.setAttribute('role', 'tooltip');
      popup.id = 'aggrio-field-help';
      popup.hidden = true;
      this.document.body.append(popup);
      const hide = () => {
        active?.setAttribute('aria-expanded', 'false');
        active = null;
        popup.hidden = true;
      };
      const show = (icon: HTMLElement, text: string) => {
        hide();
        active = icon;
        icon.setAttribute('aria-expanded', 'true');
        popup.textContent = text;
        popup.hidden = false;
        const rect = icon.getBoundingClientRect();
        const width = popup.getBoundingClientRect().width;
        popup.style.left = `${Math.max(12, Math.min(rect.left, window.innerWidth - width - 12))}px`;
        const height = popup.getBoundingClientRect().height;
        popup.style.top = `${rect.bottom + height + 12 < window.innerHeight ? rect.bottom + 8 : Math.max(12, rect.top - height - 8)}px`;
      };
      const decorate = () => {
        if (active && !root.contains(active)) hide();
        root.querySelectorAll<HTMLLabelElement>('label').forEach((label) => {
          const control = (
            label.htmlFor
              ? this.document.getElementById(label.htmlFor)
              : label.querySelector('input,select,textarea')
          ) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;
          if (
            !control ||
            (control instanceof window.HTMLInputElement &&
              ['hidden', 'submit', 'button'].includes(control.type))
          )
            return;
          const copy = label.cloneNode(true) as HTMLElement;
          copy
            .querySelectorAll(
              'input,select,textarea,button,small,.hint,.error,.field-help-icon,.field-help-description',
            )
            .forEach((node) => node.remove());
          const caption =
            copy.textContent
              ?.replace(/\s+/g, ' ')
              .trim()
              .replace(/\s*\*$/, '') ?? '';
          if (!caption) return;
          const text = fieldHelpText(caption, control);
          if (
            !control.hasAttribute('aria-labelledby') &&
            (!control.hasAttribute('aria-label') || control.dataset['fieldHelpLabel'] === 'true')
          ) {
            control.setAttribute('aria-label', caption);
            control.dataset['fieldHelpLabel'] = 'true';
          }
          let icon = label.querySelector<HTMLElement>('.field-help-icon');
          if (!icon) {
            icon = this.document.createElement('span');
            icon.className = 'field-help-icon';
            icon.textContent = '¡';
            icon.setAttribute('role', 'button');
            icon.tabIndex = 0;
            icon.setAttribute('aria-expanded', 'false');
            const description = this.document.createElement('span');
            description.className = 'field-help-description';
            description.hidden = true;
            description.id = `field-help-description-${++this.sequence}`;
            label.append(description);
            icon.dataset['description'] = description.id;
            icon.setAttribute('aria-describedby', description.id);
            const ids = new Set(
              (control.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean),
            );
            ids.add(description.id);
            control.setAttribute('aria-describedby', [...ids].join(' '));
            // Put the icon after the caption, before an embedded control or its wrapper.
            let anchor: Node | null = control;
            while (anchor && anchor.parentNode !== label) anchor = anchor.parentNode;
            const captionLine = this.document.createElement('span');
            captionLine.className = 'field-caption';
            const captionNodes = [...label.childNodes].filter(
              (node) => node.nodeType === Node.TEXT_NODE && !!node.textContent?.trim(),
            );
            if (captionNodes.length) {
              label.insertBefore(captionLine, captionNodes[0]);
              captionNodes.forEach((node) => captionLine.append(node));
              captionLine.append(icon);
            } else if (anchor && control.type !== 'checkbox' && control.type !== 'radio')
              label.insertBefore(icon, anchor);
            else label.insertBefore(icon, description);
            const open = () => show(icon!, icon!.dataset['help'] ?? '');
            icon.addEventListener('click', (event) => {
              event.preventDefault();
              event.stopPropagation();
              open();
            });
            icon.addEventListener('keydown', (event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                open();
              }
              if (event.key === 'Escape') hide();
            });
            icon.addEventListener('focus', open);
            icon.addEventListener('blur', hide);
            icon.addEventListener('pointerenter', open);
            icon.addEventListener('pointerleave', () => {
              if (this.document.activeElement !== icon) hide();
            });
          }
          icon.dataset['help'] = text;
          const name = `Ayuda: ${caption}`;
          if (icon.getAttribute('aria-label') !== name) icon.setAttribute('aria-label', name);
          const description = this.document.getElementById(icon.dataset['description']!);
          if (description && description.textContent !== text) description.textContent = text;
        });
      };
      let queued = false;
      const observer = new MutationObserver(() => {
        if (queued) return;
        queued = true;
        queueMicrotask(() => {
          queued = false;
          decorate();
        });
      });
      observer.observe(root, { childList: true, subtree: true, characterData: true });
      decorate();
      const outside = (event: Event) => {
        if (
          active &&
          !active.contains(event.target as Node) &&
          !popup.contains(event.target as Node)
        )
          hide();
      };
      const escape = (event: KeyboardEvent) => {
        if (event.key === 'Escape') hide();
      };
      this.document.addEventListener('pointerdown', outside);
      this.document.addEventListener('keydown', escape);
      window.addEventListener('resize', hide);
      window.addEventListener('scroll', hide, true);
      this.destroy.onDestroy(() => {
        observer.disconnect();
        popup.remove();
        this.document.removeEventListener('pointerdown', outside);
        this.document.removeEventListener('keydown', escape);
        window.removeEventListener('resize', hide);
        window.removeEventListener('scroll', hide, true);
      });
    });
  }
}
