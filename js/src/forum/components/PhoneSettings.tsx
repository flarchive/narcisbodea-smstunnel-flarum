import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';

const ERRORS = ['invalid_number', 'invalid_code', 'wait', 'too_many', 'not_allowed'];

/**
 * The member's own number: add it, confirm it with a code, remove it.
 * The server only ever sends this screen a masked number.
 */
export default class PhoneSettings extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.busy = false;
    this.number = null;
    this.step = 'number';
    this.numberInput = Stream('');
    this.codeInput = Stream('');
    this.message = null;
    this.isError = false;

    this.call('GET', '/phone')
      .then((state) => {
        this.number = state.number;
        this.step = state.pending && !state.number ? 'code' : 'number';
      })
      .catch(() => this.fail())
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) return <LoadingIndicator display="inline" />;

    const t = (key, params) => app.translator.trans('smstunnel-sms.forum.settings.' + key, params);

    return (
      <div className="SmstunnelPhone">
        <h4>{t('heading')}</h4>
        <p className="SmstunnelPhone-intro">{t('intro')}</p>

        {this.number ? (
          <div className="SmstunnelPhone-row">
            <span className="SmstunnelPhone-number">{t('saved', { number: this.number })}</span>
            <Button className="Button" loading={this.busy} onclick={() => this.remove()}>
              {t('remove')}
            </Button>
          </div>
        ) : null}

        {!this.number && this.step === 'number' ? (
          <div className="SmstunnelPhone-row">
            <input
              className="FormControl"
              type="tel"
              autocomplete="tel"
              maxlength="16"
              placeholder="+40712345678"
              aria-label={t('number_label')}
              bidi={this.numberInput}
            />
            <Button className="Button Button--primary" loading={this.busy} onclick={() => this.sendCode()}>
              {t('send_code')}
            </Button>
          </div>
        ) : null}

        {!this.number && this.step === 'code' ? (
          <div className="SmstunnelPhone-row">
            <input
              className="FormControl"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              aria-label={t('code_label')}
              placeholder={t('code_label')}
              bidi={this.codeInput}
            />
            <Button className="Button Button--primary" loading={this.busy} onclick={() => this.confirm()}>
              {t('confirm')}
            </Button>
            <Button className="Button Button--link" onclick={() => this.restart()}>
              {t('change')}
            </Button>
          </div>
        ) : null}

        {this.message ? (
          <p className={'SmstunnelPhone-message' + (this.isError ? ' SmstunnelPhone-message--error' : '')} role="status">
            {this.message}
          </p>
        ) : null}
      </div>
    );
  }

  sendCode() {
    this.run('POST', '/phone/code', { number: this.numberInput().trim() }, () => {
      this.step = 'code';
      this.codeInput('');
      this.say(app.translator.trans('smstunnel-sms.forum.settings.sent'));
    });
  }

  confirm() {
    this.run('POST', '/phone/confirm', { code: this.codeInput().trim() }, (state) => {
      this.number = state.number;
      this.step = 'number';
      this.numberInput('');
      this.codeInput('');
      this.say(app.translator.trans('smstunnel-sms.forum.settings.confirmed'));
    });
  }

  remove() {
    if (!confirm(app.translator.trans('smstunnel-sms.forum.settings.remove_confirm', {}, true))) return;

    this.run('DELETE', '/phone', undefined, () => {
      this.number = null;
      this.step = 'number';
      this.say(app.translator.trans('smstunnel-sms.forum.settings.removed'));
    });
  }

  restart() {
    this.step = 'number';
    this.message = null;
    this.isError = false;
  }

  run(method, path, body, done) {
    if (this.busy) return;

    this.busy = true;
    this.message = null;
    this.isError = false;

    this.call(method, path, body)
      .then(done)
      .catch((error) => this.fail(error))
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  call(method, path, body) {
    return app.request({
      method,
      url: app.forum.attribute('apiUrl') + '/smstunnel' + path,
      body,
      // Handled by the caller, in place of the default alert.
      errorHandler: () => {},
    });
  }

  say(message) {
    this.message = message;
    this.isError = false;
  }

  fail(error) {
    const reason = error && error.response && error.response.error;
    const key = ERRORS.includes(reason) ? reason : 'generic';

    this.message = app.translator.trans('smstunnel-sms.forum.errors.' + key);
    this.isError = true;
  }
}
