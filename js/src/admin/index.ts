import app from 'flarum/admin/app';
import extenders from './extend';

export { extenders as extend };

app.initializers.add('ernestdefoe-community-reviews', () => {
  /* everything is declared in ./extend */
});
