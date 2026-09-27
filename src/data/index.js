/**
 * Barrel for the demo dataset.
 *
 * Everything the old Eloquent models and seeders provided now lives in plain
 * reactive modules. Pages import from here rather than reaching into individual
 * files, so the shape of the data has one public surface.
 */

export * from './enums';
export * from './settings';
export * from './services';
export * from './inventory';
export * from './admins';
export * from './users';
export * from './appointments';
export * from './availability';
export * from './promos';
export * from './terms';
export * from './messages';
export * from './notifications';
export * from './reports';
