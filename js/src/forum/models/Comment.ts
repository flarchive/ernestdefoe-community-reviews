import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Item from './Item';

/** A comment on an item, optionally in reply to one of its reviews (MyBB product comments). */
export default class Comment extends Model {
  body = Model.attribute<string | undefined>('body');
  bodyHtml = Model.attribute<string>('bodyHtml');
  reviewId = Model.attribute<number | null>('reviewId');
  replyToName = Model.attribute<string | null>('replyToName');
  isOfficial = Model.attribute<boolean>('isOfficial');
  itemPath = Model.attribute<string | null>('itemPath');
  itemName = Model.attribute<string | null>('itemName');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  editedAt = Model.attribute<Date | null, string | null>('editedAt', Model.transformDate);
  canEdit = Model.attribute<boolean>('canEdit');
  canDelete = Model.attribute<boolean>('canDelete');
  canReport = Model.attribute<boolean>('canReport');
  item = Model.hasOne<Item>('item');
  user = Model.hasOne<User>('user');
}
