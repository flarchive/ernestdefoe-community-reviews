import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import Category from '../forum/models/Category';
import Criterion from '../forum/models/Criterion';
import ReviewsPage from './ReviewsPage';

const EXT = 'ernestdefoe-community-reviews';
const t = (key: string) => app.translator.trans(`${EXT}.admin.permissions.${key}`, {}, true) as unknown as string;

/*
 * The admin page (categories with their criteria, every MyBB setting, merge) and the permission grid rows. MyBB's
 * three group settings (edit own, delete own, moderate) and its "super moderators" switch live in the grid now.
 */
export default [
  new Extend.Store().add('review-categories', Category).add('review-criteria', Criterion),
  new Extend.Admin()
    .page(ReviewsPage)
    .permission(() => ({ icon: 'fas fa-star-half-stroke', label: t('view'), permission: 'communityReviews.view', allowGuest: true }), 'view', 60)
    .permission(() => ({ icon: 'fas fa-box-open', label: t('add_items'), permission: 'communityReviews.addItems' }), 'start', 60)
    .permission(() => ({ icon: 'fas fa-pen', label: t('write'), permission: 'communityReviews.write' }), 'reply', 60)
    .permission(() => ({ icon: 'far fa-comment', label: t('comment'), permission: 'communityReviews.comment' }), 'reply', 59)
    .permission(() => ({ icon: 'fas fa-thumbs-up', label: t('vote'), permission: 'communityReviews.vote' }), 'reply', 58)
    .permission(() => ({ icon: 'fas fa-pencil', label: t('edit_own'), permission: 'communityReviews.editOwn' }), 'reply', 57)
    .permission(() => ({ icon: 'far fa-trash-can', label: t('delete_own'), permission: 'communityReviews.deleteOwn' }), 'reply', 56)
    .permission(() => ({ icon: 'fas fa-check-double', label: t('skip_approval'), permission: 'communityReviews.skipApproval' }), 'moderate', 60)
    .permission(() => ({ icon: 'fas fa-shield-halved', label: t('moderate'), permission: 'communityReviews.moderate' }), 'moderate', 59),
];
