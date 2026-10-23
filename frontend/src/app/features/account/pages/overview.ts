import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { errorMessage } from '../../auth/error-message';
import { ProducerSidebar } from '../producer-sidebar';
import { ProducerDataService, ProducerOverview } from '../services/producer-data.service';

@Component({
  selector: 'app-account-overview',
  standalone: true,
  imports: [CommonModule, RouterLink, ProducerSidebar],
  templateUrl: './overview.html',
  styleUrl: './account-shell.scss',
})
export class Overview implements OnInit {
  private readonly producerData = inject(ProducerDataService);

  readonly isLoading = signal(true);
  readonly error = signal('');
  readonly data = signal<ProducerOverview | null>(null);

  ngOnInit(): void {
    this.producerData.getOverview().subscribe({
      next: (data) => {
        this.data.set(data);
        this.isLoading.set(false);
      },
      error: (error: unknown) => {
        this.error.set(errorMessage(error, 'No fue posible cargar los datos de tu cuenta.'));
        this.isLoading.set(false);
      },
    });
  }
}
