// Global type augmentations for Bootstrap modal plugin

interface JQuery {
  modal(arg?: string | Record<string, unknown>): this;
  modal(action: 'show' | 'hide' | 'toggle' | 'handleUpdate'): this;
}
