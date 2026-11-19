import app from 'flarum/admin/app';

export const EXT = 'ernestdefoe-community-reviews';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`${EXT}.admin.${key}`, params);

export const tx = (key: string, params: Record<string, unknown> = {}) =>
  app.translator.trans(`${EXT}.admin.${key}`, params, true) as unknown as string;

/** POST to one of our API endpoints. */
export const post = (path: string, body: Record<string, unknown>) =>
  app.request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}${path}`, body });

/** A save or delete whose error app.request has already shown: nothing more to say. */
export const shown = () => {
  /* app.request already showed the error */
};
