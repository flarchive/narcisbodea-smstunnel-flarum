import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import { call, failure, noSubmit } from '../api';

const t = (key: string) => app.translator.trans('smstunnel-sms.admin.' + key);

/**
 * The API key is write-only here: the server answers whether one is saved, never the key.
 */
export default class KeySetting extends Component {
  loading = true;
  busy = false;
  hasKey = false;
  input = Stream('');
  message: any = null;
  isError = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    call('GET', '')
      .then((state) => (this.hasKey = state.hasKey))
      .catch((error) => this.show(failure(error), true))
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading) return <LoadingIndicator display="inline" />;

    return (
      <div className="SmstunnelAdmin">
        <p>{t('intro')}</p>
        <h3>{t('key.heading')}</h3>
        <p className="helpText">{this.hasKey ? t('key.saved') : t('key.none')}</p>
        <div className="SmstunnelAdmin-row">
          <input
            className="FormControl"
            type="password"
            autocomplete="off"
            aria-label={t('key.label')}
            placeholder={t('key.placeholder')}
            bidi={this.input}
            onkeydown={noSubmit}
          />
          <Button className="Button Button--primary" type="button" loading={this.busy} onclick={() => this.save()}>
            {t('key.save')}
          </Button>
          {this.hasKey ? (
            <Button className="Button" type="button" loading={this.busy} onclick={() => this.remove()}>
              {t('key.remove')}
            </Button>
          ) : null}
        </div>
        <p className="helpText">{t('get_key')}</p>
        {this.message ? (
          <p className={'SmstunnelAdmin-message' + (this.isError ? ' SmstunnelAdmin-message--error' : '')} role="status">
            {this.message}
          </p>
        ) : null}
      </div>
    );
  }

  save() {
    this.run(call('POST', '/key', { key: this.input().trim() }), () => {
      this.hasKey = true;
      this.input('');
      this.show(t('key.stored'), false);
    });
  }

  remove() {
    this.run(call('DELETE', '/key'), () => {
      this.hasKey = false;
      this.show(t('key.removed'), false);
    });
  }

  run(request: Promise<any>, done: (value: any) => void) {
    this.busy = true;
    this.message = null;

    request
      .then(done)
      .catch((error) => this.show(failure(error), true))
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  show(message: any, isError: boolean) {
    this.message = message;
    this.isError = isError;
  }
}
