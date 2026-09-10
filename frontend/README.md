# Frontend

This project was generated using [Angular CLI](https://github.com/angular/angular-cli) version 21.2.23.

## Development server

To start a local development server, run:

```bash
cd frontend
ng serve
```

Once the server is running, open your browser and navigate to `http://localhost:4200/`. The application will automatically reload whenever you modify any of the source files.

## Estructure Project

```bash
frontend/
├── public/
│   ├── favicon.ico
│   └── logo-aggrio.svg
├── src/
│   ├── environments/
│   │   ├── environment.ts
│   │   └── environment.development.ts
│   ├── app/
│   │   ├── core/
│   │   │   ├── auth/
│   │   │   │   ├── auth.service.ts
│   │   │   │   ├── auth.guard.ts
│   │   │   │   ├── auth.interceptor.ts
│   │   │   │   └── auth.model.ts
│   │   │   ├── services/
│   │   │   │   └── error-handler.service.ts
│   │   │   ├── models/
│   │   │   │   ├── api-response.model.ts
│   │   │   │   └── paginated-response.model.ts
│   │   │   └── enums/
│   │   │       ├── estado-lote.enum.ts
│   │   │       ├── tipo-multimedia.enum.ts
│   │   │       └── rol-usuario.enum.ts
│   │   │
│   │   ├── shared/
│   │   │   ├── layout/
│   │   │   │   └── shell/
│   │   │   │       ├── shell.ts
│   │   │   │       ├── shell.html
│   │   │   │       └── shell.scss
│   │   │   ├── components/
│   │   │   │   ├── loading-spinner/
│   │   │   │   │   ├── loading-spinner.ts
│   │   │   │   │   ├── loading-spinner.html
│   │   │   │   │   └── loading-spinner.scss
│   │   │   │   ├── empty-state/
│   │   │   │   │   ├── empty-state.ts
│   │   │   │   │   ├── empty-state.html
│   │   │   │   │   └── empty-state.scss
│   │   │   │   └── confirm-dialog/
│   │   │   │       ├── confirm-dialog.ts
│   │   │   │       ├── confirm-dialog.html
│   │   │   │       └── confirm-dialog.scss
│   │   │   └── pipes/
│   │   │       └── fecha.pipe.ts
│   │   │
│   │   ├── features/
│   │   │   ├── auth/
│   │   │   │   ├── auth.routes.ts
│   │   │   │   └── pages/
│   │   │   │       └── login/
│   │   │   │           ├── login.ts
│   │   │   │           ├── login.html
│   │   │   │           └── login.scss
│   │   │   │
│   │   │   ├── estates/
│   │   │   │   ├── estates.routes.ts
│   │   │   │   ├── models/
│   │   │   │   │   └── estate.model.ts
│   │   │   │   ├── services/
│   │   │   │   │   └── estate.service.ts
│   │   │   │   └── pages/
│   │   │   │       ├── estate-list/
│   │   │   │       │   ├── estate-list.ts
│   │   │   │       │   ├── estate-list.html
│   │   │   │       │   └── estate-list.scss
│   │   │   │       ├── estate-form/
│   │   │   │       │   ├── estate-form.ts
│   │   │   │       │   ├── estate-form.html
│   │   │   │       │   └── estate-form.scss
│   │   │   │       └── estate-detail/
│   │   │   │           ├── estate-detail.ts
│   │   │   │           ├── estate-detail.html
│   │   │   │           └── estate-detail.scss
│   │   │   │
│   │   │   ├── lots/
│   │   │   │   ├── lots.routes.ts
│   │   │   │   ├── models/
│   │   │   │   │   └── lot.model.ts
│   │   │   │   ├── services/
│   │   │   │   │   └── lot.service.ts
│   │   │   │   └── pages/
│   │   │   │       ├── lot-list/
│   │   │   │       │   ├── lot-list.ts
│   │   │   │       │   ├── lot-list.html
│   │   │   │       │   └── lot-list.scss
│   │   │   │       └── lot-form/
│   │   │   │           ├── lot-form.ts
│   │   │   │           ├── lot-form.html
│   │   │   │           └── lot-form.scss
│   │   │   │
│   │   │   ├── activities/
│   │   │   │   ├── activities.routes.ts
│   │   │   │   ├── models/
│   │   │   │   │   └── activity.model.ts
│   │   │   │   ├── services/
│   │   │   │   │   └── activity.service.ts
│   │   │   │   └── pages/
│   │   │   │       ├── activity-list/
│   │   │   │       │   ├── activity-list.ts
│   │   │   │       │   ├── activity-list.html
│   │   │   │       │   └── activity-list.scss
│   │   │   │       └── activity-form/
│   │   │   │           ├── activity-form.ts
│   │   │   │           ├── activity-form.html
│   │   │   │           └── activity-form.scss
│   │   │   │
│   │   │   ├── multimedia/
│   │   │   │   ├── multimedia.routes.ts
│   │   │   │   ├── models/
│   │   │   │   │   └── multimedia.model.ts
│   │   │   │   ├── services/
│   │   │   │   │   ├── multimedia.service.ts
│   │   │   │   │   └── presigned-url.service.ts
│   │   │   │   └── pages/
│   │   │   │       ├── multimedia-gallery/
│   │   │   │       │   ├── multimedia-gallery.ts
│   │   │   │       │   ├── multimedia-gallery.html
│   │   │   │       │   └── multimedia-gallery.scss
│   │   │   │       └── multimedia-upload/
│   │   │   │           ├── multimedia-upload.ts
│   │   │   │           ├── multimedia-upload.html
│   │   │   │           └── multimedia-upload.scss
│   │   │   │
│   │   │   └── iot/
│   │   │       ├── iot.routes.ts
│   │   │       ├── models/
│   │   │       │   └── lectura-iot.model.ts
│   │   │       ├── services/
│   │   │       │   └── lectura-iot.service.ts
│   │   │       └── pages/
│   │   │           └── iot-dashboard/
│   │   │               ├── iot-dashboard.ts
│   │   │               ├── iot-dashboard.html
│   │   │               └── iot-dashboard.scss
│   │   │
│   │   ├── app.config.ts
│   │   ├── app.routes.ts
│   │   ├── app.html
│   │   ├── app.scss
│   │   ├── app.spec.ts
│   │   └── app.ts
│   ├── index.html
│   ├── styles.scss
│   └── main.ts
├── angular.json
├── package.json
├── proxy.conf.json
├── tsconfig.json
├── tsconfig.app.json
├── tsconfig.spec.json
└── README.md

```

## Code scaffolding

Angular CLI includes powerful code scaffolding tools. To generate a new component, run:

```bash
ng generate component component-name
```

For a complete list of available schematics (such as `components`, `directives`, or `pipes`), run:

```bash
ng generate --help
```

## Building

To build the project run:

```bash
ng build
```

This will compile your project and store the build artifacts in the `dist/` directory. By default, the production build optimizes your application for performance and speed.

## Running unit tests

To execute unit tests with the [Vitest](https://vitest.dev/) test runner, use the following command:

```bash
ng test
```

## Running end-to-end tests

For end-to-end (e2e) testing, run:

```bash
ng e2e
```

Angular CLI does not come with an end-to-end testing framework by default. You can choose one that suits your needs.

## Additional Resources

For more information on using the Angular CLI, including detailed command references, visit the [Angular CLI Overview and Command Reference](https://angular.dev/tools/cli) page.
