import { Component } from '@angular/core';
import { AppSidebar } from '../shared/app-sidebar';

@Component({
  selector: 'app-admin-sidebar',
  standalone: true,
  imports: [AppSidebar],
  template: '<app-sidebar />',
  styles: [':host { display: contents; }'],
})
export class AdminSidebar {}
