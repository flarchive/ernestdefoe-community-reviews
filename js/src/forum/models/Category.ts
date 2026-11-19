import Model from 'flarum/common/Model';
import type Criterion from './Criterion';

export interface ItemField {
  key: string;
  label: string;
  type: 'text' | 'number' | 'choice';
  options: string[];
  filter: boolean;
}

/** A review category (MyBB "category") with its criteria (MyBB "fields"). */
export default class Category extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  icon = Model.attribute<string | null>('icon');
  color = Model.attribute<string | null>('color');
  position = Model.attribute<number>('position');
  parentId = Model.attribute<number | null>('parentId');
  itemFields = Model.attribute<ItemField[] | null>('itemFields');
  itemCount = Model.attribute<number>('itemCount');
  reviewCount = Model.attribute<number>('reviewCount');
  criteria = Model.hasMany<Criterion>('criteria');
}
