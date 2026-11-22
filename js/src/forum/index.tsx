import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PhoneSettings from './components/PhoneSettings';

app.initializers.add('smstunnel-sms', () => {
  // Both are in Flarum's lazy-loaded settings page, so they are named by path.
  extend('flarum/forum/components/NotificationGrid', 'notificationMethods', function (items) {
    if (!app.forum.attribute('smstunnelMemberTexts')) return;

    items.add('smstunnel', {
      name: 'smstunnel',
      icon: 'fas fa-sms',
      label: app.translator.trans('smstunnel-sms.forum.settings.column'),
    });
  });

  extend('flarum/forum/components/SettingsPage', 'notificationsItems', function (items) {
    if (!app.forum.attribute('smstunnelMemberTexts')) return;

    items.add('smstunnelPhone', <PhoneSettings />, 95);
  });
});
