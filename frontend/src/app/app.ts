import { Component, ElementRef, afterNextRender, inject, signal } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ThemeService } from './core/theme/theme.service';
import { FieldHelp } from './shared/field-help';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  protected readonly title = signal('frontend');

  constructor() {
    inject(ThemeService);
    const help = inject(FieldHelp);
    const root = inject(ElementRef<HTMLElement>);
    afterNextRender(() => help.start(root.nativeElement));
  }
}
