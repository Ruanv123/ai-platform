<?php

namespace App\Enums;

enum WhatsAppBusinessCategory: string
{
    case Other = 'OTHER';
    case Auto = 'AUTO';
    case Beauty = 'BEAUTY';
    case Apparel = 'APPAREL';
    case Edu = 'EDU';
    case Entertain = 'ENTERTAIN';
    case EventPlan = 'EVENT_PLAN';
    case Finance = 'FINANCE';
    case Grocery = 'GROCERY';
    case Govt = 'GOVT';
    case Hotel = 'HOTEL';
    case Health = 'HEALTH';
    case Nonprofit = 'NONPROFIT';
    case ProfServices = 'PROF_SERVICES';
    case Retail = 'RETAIL';
    case Travel = 'TRAVEL';
    case Restaurant = 'RESTAURANT';
    case Alcohol = 'ALCOHOL';
    case OnlineGambling = 'ONLINE_GAMBLING';
    case PhysicalGambling = 'PHYSICAL_GAMBLING';
    case OtcDrugs = 'OTC_DRUGS';

    public function label(): string
    {
        return match ($this) {
            self::Other => 'Outro',
            self::Auto => 'Automotivo',
            self::Beauty => 'Beleza',
            self::Apparel => 'Roupas e acessórios',
            self::Edu => 'Educação',
            self::Entertain => 'Entretenimento',
            self::EventPlan => 'Planejamento de eventos',
            self::Finance => 'Finanças',
            self::Grocery => 'Mercearia',
            self::Govt => 'Governo',
            self::Hotel => 'Hotelaria',
            self::Health => 'Saúde',
            self::Nonprofit => 'Organização sem fins lucrativos',
            self::ProfServices => 'Serviços profissionais',
            self::Retail => 'Varejo',
            self::Travel => 'Viagens',
            self::Restaurant => 'Restaurante',
            self::Alcohol => 'Bebidas alcoólicas',
            self::OnlineGambling => 'Jogos de azar online',
            self::PhysicalGambling => 'Jogos de azar presenciais',
            self::OtcDrugs => 'Medicamentos sem prescrição',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases(),
        );
    }
}
