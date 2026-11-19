import Model from 'flarum/common/Model';
import type Category from './Category';

/** What every review in a category rates 1-5 and comments on. */
export default class Criterion extends Model {
  name = Model.attribute<string>('name');
  weight = Model.attribute<number>('weight');
  position = Model.attribute<number>('position');
  category = Model.hasOne<Category>('category');
}
