import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Item from './Item';

/** A member's report of an item, review or comment, for the moderators' queue. */
export default class Report extends Model {
  subjectType = Model.attribute<'item' | 'review' | 'comment'>('subjectType');
  subjectId = Model.attribute<number>('subjectId');
  reason = Model.attribute<string | null>('reason');
  excerpt = Model.attribute<string | null>('excerpt');
  resolved = Model.attribute<boolean>('resolved');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  item = Model.hasOne<Item>('item');
  user = Model.hasOne<User>('user');
}
