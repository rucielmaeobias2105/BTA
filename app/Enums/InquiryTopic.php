<?php

namespace App\Enums;

enum InquiryTopic: string
{
    case GeneralInquiry = 'general_inquiry';
    case BookingQuestion = 'booking_question';
    case ServiceQuestion = 'service_question';
    case PricingQuestion = 'pricing_question';
    case ProductInquiry = 'product_inquiry';
    case FeedbackComplaint = 'feedback_complaint';
    case Others = 'others';

    public function label(): string
    {
        return match ($this) {
            self::GeneralInquiry => 'General Inquiry',
            self::BookingQuestion => 'Booking Question',
            self::ServiceQuestion => 'Service Question',
            self::PricingQuestion => 'Pricing / Packages',
            self::ProductInquiry => 'Product Inquiry',
            self::FeedbackComplaint => 'Feedback or Complaint',
            self::Others => 'Others',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
