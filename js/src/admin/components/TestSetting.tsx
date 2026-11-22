import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import { call, errorText, failure, noSubmit } from '../api';

const t = (key: string) => app.translator.trans('smstunnel-sms.admin.test.' + key);

export default class TestSetting extends Component {
  busy = false;
  input = Stream('');
  message: any = null;
  isError = false;

  view() {
    return (
      <div className="SmstunnelAdmin">
        <h3>{t('heading')}</h3>
        <p className="helpText">{t('help')}</p>
        <div className="SmstunnelAdmin-row">
          <input
            className="FormControl"
            type="tel"
            autocomplete="off"
            maxlength="16"
            placeholder="+40712345678"
            aria-label={t('heading')}
            bidi={this.input}
            onkeydown={noSubmit}
          />
          <Button className="Button" type="button" loading={this.busy} onclick={() => this.send()}>
            {t('send')}
          </Button>
        </div>
        {this.message ? (
          <p className={'SmstunnelAdmin-message' + (this.isError ? ' SmstunnelAdmin-message--error' : '')} role="status">
            {this.message}
          </p>
        ) : null}
      </div>
    );
  }

  send() {
    this.busy = true;
    this.message = null;

    call('POST', '/test', { number: this.input().trim() })
      .then((state) => {
        this.isError = state.result !== 'accepted';
        this.message = this.isError ? errorText(state.result) : t('accepted');
      })
      .catch((error) => {
        this.isError = true;
        this.message = failure(error);
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }
}
