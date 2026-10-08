import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import AuthenticationFields from './authentication-fields';
import CallPermissionNotice from './call-permission-notice';
import CarouselCardsField from './carousel-cards-field';
import CouponFields from './coupon-fields';
import LimitedTimeOfferFields from './limited-time-offer-fields';
import MessageEditor from './message-editor';
import StandardButtonsField from './standard-buttons-field';
import TemplateHeaderField from './template-header-field';
import type { TemplateDraft } from './template-draft';

export default function TemplateContentStep({
    draft,
    errors,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    const format = draft.format;

    if (format === 'authentication') {
        return (
            <AuthenticationFields
                draft={draft}
                errors={errors}
                onChange={onChange}
            />
        );
    }

    const bodyError =
        errors.body ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('variables'),
        )?.[1];

    return (
        <div className="grid gap-6">
            {(format === 'standard' ||
                format === 'coupon' ||
                format === 'limited_time_offer') && (
                <TemplateHeaderField
                    draft={draft}
                    errors={errors}
                    onChange={onChange}
                />
            )}

            {format === 'limited_time_offer' && (
                <LimitedTimeOfferFields
                    draft={draft}
                    errors={errors}
                    section="offer"
                    onChange={onChange}
                />
            )}

            <MessageEditor
                id="body"
                label={
                    format === 'carousel'
                        ? 'Mensagem acima dos cards'
                        : 'Mensagem'
                }
                value={draft.body}
                maxLength={format === 'limited_time_offer' ? 600 : 1024}
                examples={draft.bodyExamples}
                descriptions={draft.bodyDescriptions}
                error={bodyError}
                onChange={(body, bodyExamples, bodyDescriptions) =>
                    onChange({ body, bodyExamples, bodyDescriptions })
                }
            />

            {format === 'carousel' && (
                <CarouselCardsField
                    draft={draft}
                    errors={errors}
                    onChange={onChange}
                />
            )}

            {format === 'coupon' && (
                <CouponFields
                    draft={draft}
                    errors={errors}
                    onChange={onChange}
                />
            )}

            {format === 'limited_time_offer' && (
                <LimitedTimeOfferFields
                    draft={draft}
                    errors={errors}
                    section="buttons"
                    onChange={onChange}
                />
            )}

            {format === 'call_permission' && <CallPermissionNotice />}

            {format === 'standard' && (
                <>
                    <section className="grid gap-2">
                        <label
                            htmlFor="footer"
                            className="text-sm font-semibold"
                        >
                            Rodapé{' '}
                            <span className="text-muted-foreground font-normal">
                                (opcional)
                            </span>
                        </label>
                        <Input
                            id="footer"
                            value={draft.footer}
                            maxLength={60}
                            onChange={(event) =>
                                onChange({ footer: event.target.value })
                            }
                            placeholder="Ex.: nome da empresa"
                        />
                        <InputError message={errors.footer} />
                    </section>
                    <StandardButtonsField
                        buttons={draft.buttons}
                        error={
                            errors.buttons ??
                            Object.entries(errors).find(([key]) =>
                                key.startsWith('buttons.'),
                            )?.[1]
                        }
                        onChange={(buttons) => onChange({ buttons })}
                    />
                </>
            )}
        </div>
    );
}
