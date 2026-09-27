import { reactive, computed } from 'vue';
import { addDays, today } from '@/lib/dates';
import { InquiryTopic } from './enums';

/**
 * Contact form submissions.
 *
 * Ported from the message rows in `database/seeders/ContentSeeder.php`,
 * including the one message an admin has already replied to.
 */

let nextId = 1;

function makeMessage({ name, email, topic, message, is_read, admin_reply = null }) {
    return reactive({
        id: nextId++,
        name,
        email,
        topic,
        message,
        is_read,
        admin_reply,
        replied_at: admin_reply ? addDays(today(), -2) : null,
        created_at: addDays(today(), -(nextId * 3)),
    });
}

export const contactMessages = reactive([
    makeMessage({
        name: 'Liza Mercado',
        email: 'liza@example.test',
        topic: InquiryTopic.PricingQuestion.value,
        message:
            'Hi! Do you have a package for hair colour plus a blowout? Also, how long does rebonding usually take?',
        is_read: false,
    }),
    makeMessage({
        name: 'Toni Alvarez',
        email: 'toni@example.test',
        topic: InquiryTopic.BookingQuestion.value,
        message: 'Can I reschedule my appointment to Saturday morning instead? Thank you!',
        is_read: false,
    }),
    makeMessage({
        name: 'Grace Lim',
        email: 'grace@example.test',
        topic: InquiryTopic.ServiceQuestion.value,
        message: 'I have a gel allergy. Which of your nail services would you recommend instead?',
        is_read: true,
        admin_reply:
            'Hi Grace! We suggest the Glow Manicure or Classic Pedicure with regular polish — both avoid gel products entirely. Reply here or call us to book.',
    }),
]);

export const unreadMessages = computed(() => contactMessages.filter((message) => !message.is_read));

export function findMessage(id) {
    return contactMessages.find((message) => String(message.id) === String(id)) ?? null;
}

export function messagesByTopic() {
    return contactMessages.reduce((grouped, message) => {
        (grouped[message.topic] ??= []).push(message);

        return grouped;
    }, {});
}
