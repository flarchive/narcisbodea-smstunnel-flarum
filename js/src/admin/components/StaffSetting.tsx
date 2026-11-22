import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';
import { call, failure, noSubmit } from '../api';

const t = (key: string) => app.translator.trans('smstunnel-sms.admin.staff.' + key);

interface StaffRow {
  id: number;
  number: string;
  confirmed: boolean;
}

/**
 * Staff numbers, each confirmed with a code. The server only sends this screen masked numbers.
 */
export default class StaffSetting extends Component {
  loading = true;
  busy = false;
  staff: StaffRow[] = [];
  input = Stream('');
  codes: Record<number, any> = {};
  message: any = null;

  oninit(vnode: any) {
    super.oninit(vnode);

    call('GET', '')
      .then((state) => (this.staff = state.staff))
      .catch((error) => (this.message = failure(error)))
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  codeStream(id: number) {
    return (this.codes[id] ??= Stream(''));
  }

  view() {
    if (this.loading) return <LoadingIndicator display="inline" />;

    return (
      <div className="SmstunnelAdmin">
        <h3>{t('heading')}</h3>
        <p className="helpText">{t('help')}</p>

        {this.staff.length ? (
          <ul className="SmstunnelAdmin-staff">
            {this.staff.map((row) => (
              <li key={row.id}>
                <span className="SmstunnelAdmin-number">{row.number}</span>
                <span className="SmstunnelAdmin-state">{row.confirmed ? t('confirmed') : t('waiting')}</span>
                {!row.confirmed ? (
                  <input
                    className="FormControl"
                    style="width: 110px"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="6"
                    aria-label={t('code_label')}
                    placeholder={t('code_label')}
                    bidi={this.codeStream(row.id)}
                    onkeydown={noSubmit}
                  />
                ) : null}
                {!row.confirmed ? (
                  <Button className="Button Button--primary" type="button" loading={this.busy} onclick={() => this.confirm(row.id)}>
                    {t('confirm')}
                  </Button>
                ) : null}
                <Button className="Button" type="button" loading={this.busy} onclick={() => this.remove(row.id)}>
                  {t('remove')}
                </Button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="helpText">{t('empty')}</p>
        )}

        <div className="SmstunnelAdmin-row">
          <input
            className="FormControl"
            type="tel"
            autocomplete="off"
            maxlength="16"
            placeholder="+40712345678"
            aria-label={t('number_label')}
            bidi={this.input}
            onkeydown={noSubmit}
          />
          <Button className="Button" type="button" loading={this.busy} onclick={() => this.add()}>
            {t('add')}
          </Button>
        </div>

        {this.message ? (
          <p className="SmstunnelAdmin-message SmstunnelAdmin-message--error" role="status">
            {this.message}
          </p>
        ) : null}
      </div>
    );
  }

  add() {
    this.run(call('POST', '/staff', { number: this.input().trim() }), (state) => {
      this.staff = state.staff;
      this.input('');
    });
  }

  confirm(id: number) {
    this.run(call('POST', `/staff/${id}/confirm`, { code: this.codeStream(id)().trim() }), (state) => {
      this.staff = state.staff;
    });
  }

  remove(id: number) {
    this.run(call('DELETE', `/staff/${id}`), (state) => {
      this.staff = state.staff;
    });
  }

  run(request: Promise<any>, done: (value: any) => void) {
    this.busy = true;
    this.message = null;

    request
      .then(done)
      .catch((error) => (this.message = failure(error)))
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }
}
