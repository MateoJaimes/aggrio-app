import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { FieldHelp } from './field-help';
import { fieldHelpText } from './field-help-text';

describe('Ayuda de los campos', () => {
  let root: HTMLDivElement;
  beforeEach(() => {
    TestBed.configureTestingModule({});
    root = document.createElement('div');
    document.body.append(root);
  });
  afterEach(() => {
    TestBed.resetTestingModule();
    root.remove();
  });

  it('añade el icono al título y conserva la descripción existente', () => {
    root.innerHTML =
      '<label>Superficie (hectáreas)<input required aria-describedby="existing"></label>';
    TestBed.inject(FieldHelp).start(root);
    const icon = root.querySelector<HTMLElement>('.field-help-icon')!;
    expect(icon.textContent).toBe('¡');
    expect(icon.parentElement!.className).toBe('field-caption');
    expect(root.querySelector('input')!.getAttribute('aria-label')).toBe('Superficie (hectáreas)');
    expect(root.querySelector('input')!.getAttribute('aria-describedby')).toContain('existing');
    icon.click();
    const popup = document.querySelector<HTMLElement>('.field-help-popup')!;
    expect(popup.hidden).toBe(false);
    expect(popup.textContent).toContain('10000 m²');
    expect(popup.textContent).toContain('obligatorio');
  });

  it('funciona con teclado sin enviar el formulario y cierra con Escape', () => {
    root.innerHTML =
      '<form><label for="cost">Precio por unidad (COP)</label><input id="cost"></form>';
    TestBed.inject(FieldHelp).start(root);
    const icon = root.querySelector<HTMLElement>('.field-help-icon')!;
    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
    icon.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(icon.getAttribute('aria-expanded')).toBe('true');
    icon.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(document.querySelector<HTMLElement>('.field-help-popup')!.hidden).toBe(true);
  });

  it('incluye nuevos pasos sin duplicar iconos ni modificar los datos', async () => {
    TestBed.inject(FieldHelp).start(root);
    root.innerHTML = '<label>¿Cuándo esperas cosechar?<input type="date"></label>';
    await Promise.resolve();
    await Promise.resolve();
    expect(root.querySelectorAll('.field-help-icon')).toHaveLength(1);
    root.querySelector('input')!.value = '2027-01-01';
    root.append(document.createTextNode(' '));
    await Promise.resolve();
    await Promise.resolve();
    expect(root.querySelectorAll('.field-help-icon')).toHaveLength(1);
    expect(root.querySelector('input')!.value).toBe('2027-01-01');
  });

  it('no activa una casilla al tocar su ayuda', () => {
    root.innerHTML =
      '<label><input type="checkbox">Recordar este navegador durante 30 días</label>';
    TestBed.inject(FieldHelp).start(root);
    root.querySelector<HTMLElement>('.field-help-icon')!.click();
    expect(root.querySelector('input')!.checked).toBe(false);
  });

  it('distingue el nombre de un lote de la selección de un lote existente', () => {
    const input = document.createElement('input');
    expect(fieldHelpText('¿Cómo llamas a este lote?', input)).toContain('nombre corto');
    expect(fieldHelpText('¿En qué lote?', input)).toContain('Selecciona la parte');
  });
});
