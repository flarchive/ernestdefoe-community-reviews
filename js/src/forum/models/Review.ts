import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type Item from './Item';

export interface ReviewScore {
  criterionId: number;
  name: string;
  score: number;
  comment: string | null;
}

export interface ReviewPhoto {
  id: number;
  url: string;
  thumbUrl: string;
  width: number;
  height: number;
}

/** A member's review: a score and comment per criterion, General thoughts, price, link, merchant, photos. */
export default class Review extends Model {
  rating = Model.attribute<number>('rating');
  previousRating = Model.attribute<number | null>('previousRating');
  title = Model.attribute<string | null>('title');
  body = Model.attribute<string | null | undefined>('body');
  bodyHtml = Model.attribute<string | null>('bodyHtml');
  scores = Model.attribute<ReviewScore[]>('scores');
  price = Model.attribute<string | null>('price');
  url = Model.attribute<string | null>('url');
  photos = Model.attribute<ReviewPhoto[]>('photos');
  ownedFor = Model.attribute<string | null | undefined>('ownedFor');
  pros = Model.attribute<string[] | null | undefined>('pros');
  cons = Model.attribute<string[] | null | undefined>('cons');
  isVerified = Model.attribute<boolean | undefined>('isVerified');
  status = Model.attribute<'approved' | 'pending' | undefined>('status');
  helpfulCount = Model.attribute<number | undefined>('helpfulCount');
  votedHelpful = Model.attribute<boolean | undefined>('votedHelpful');
  commentCount = Model.attribute<number>('commentCount');
  itemPath = Model.attribute<string | null>('itemPath');
  itemName = Model.attribute<string | null>('itemName');
  createdAt = Model.attribute<Date, string>('createdAt', Model.transformDate);
  editedAt = Model.attribute<Date | null, string | null>('editedAt', Model.transformDate);

  canEdit = Model.attribute<boolean>('canEdit');
  canDelete = Model.attribute<boolean>('canDelete');
  canModerate = Model.attribute<boolean>('canModerate');
  canVote = Model.attribute<boolean>('canVote');
  canReport = Model.attribute<boolean>('canReport');

  item = Model.hasOne<Item>('item');
  user = Model.hasOne<User>('user');
  merchant = Model.hasOne<User>('merchant');
}
