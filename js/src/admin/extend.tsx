import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import KeySetting from './components/KeySetting';
import StaffSetting from './components/StaffSetting';
import TestSetting from './components/TestSetting';

const t = (key: string) => app.translator.trans('smstunnel-sms.admin.' + key);

export default [
  new Extend.Admin()
    .customSetting(() => <KeySetting />, 100)
    .setting(
      () => ({
        setting: 'smstunnel-sms.staff_flags',
        type: 'boolean',
        label: t('switches.flags_label'),
        help: t('switches.flags_help'),
      }),
      90
    )
    .setting(
      () => ({
        setting: 'smstunnel-sms.staff_approval',
        type: 'boolean',
        label: t('switches.approval_label'),
        help: t('switches.approval_help'),
      }),
      85
    )
    .setting(
      () => ({
        setting: 'smstunnel-sms.member_texts',
        type: 'boolean',
        label: t('switches.members_label'),
        help: t('switches.members_help'),
      }),
      80
    )
    .setting(
      () => ({
        setting: 'smstunnel-sms.member_day',
        type: 'number',
        min: 1,
        max: 20,
        placeholder: '5',
        label: t('switches.member_day_label'),
        help: t('switches.member_day_help'),
      }),
      75
    )
    .setting(
      () => ({
        setting: 'smstunnel-sms.forum_day',
        type: 'number',
        min: 1,
        max: 1000,
        placeholder: '100',
        label: t('switches.forum_day_label'),
        help: t('switches.forum_day_help'),
      }),
      70
    )
    .customSetting(() => <StaffSetting />, 60)
    .customSetting(() => <TestSetting />, 50),
];
