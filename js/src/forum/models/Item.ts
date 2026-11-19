import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Category from './Category';

/** A reviewed product (MyBB "product"), with its counts and averages kept on the row. */
export default class Item extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  brand = Model.attribute<string | null>('brand');
  fields = Model.attribute<Record<string, string> | null>('fields');
  status = Model.attribute<'approved' | 'pending' | undefined>('status');
  reviewCount = Model.attribute<number>('reviewCount');
  commentCount = Model.attribute<number>('commentCount');
  viewCount = Model.attribute<number>('viewCount');
  rating = Model.attribute<number | null>('rating');
  rank = Model.attribute<number | null>('rank');
  criteriaAverages = Model.attribute<Record<string, number> | null>('criteriaAverages');
  distribution = Model.attribute<number[] | null>('distribution');
  topMentions = Model.attribute<{ pros: Record<string, number>; cons: Record<string, number> } | null | undefined>('topMentions');
  thumbnailUrl = Model.attribute<string | null>('thumbnailUrl');
  photoUrl = Model.attribute<string | null>('photoUrl');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  lastActivityAt = Model.attribute<Date | null, string | null>('lastActivityAt', Model.transformDate);

  canEdit = Model.attribute<boolean>('canEdit');
  canDelete = Model.attribute<boolean>('canDelete');
  canModerate = Model.attribute<boolean>('canModerate');
  canReview = Model.attribute<boolean>('canReview');
  canComment = Model.attribute<boolean>('canComment');
  canReport = Model.attribute<boolean>('canReport');

  category = Model.hasOne<Category>('category');
  user = Model.hasOne<User>('user');

  /** The URL slug: id plus name, like discussions. */
  urlId(): string {
    return `${this.id()}-${this.slug()}`;
  }
}
