import 'package:flutter/material.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/dimens.dart';
import '../legal_content.dart';

class PrivacyScreen extends StatelessWidget {
  const PrivacyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.legalPrivacyTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(SokoniDimens.space20),
        child: Text(locale == 'sw' ? _swBody : _enBody, style: Theme.of(context).textTheme.bodyMedium),
      ),
    );
  }
}

const _enBody = '''
Privacy Policy — version $sokoniTermsVersion

Last updated: 2026

1. What we collect
Account: name, phone number or email, and profile photo, from you directly or from Google/Apple when you sign in that way.
Seller verification: NIDA number, a photo of your national ID, and your business/trading licence — used only by our verification team, never shown publicly.
Location: your device's approximate or precise location, if you grant permission, used to show nearby sellers and products. You can decline and pick a region/district manually instead.
Usage: products you view or favourite, orders you place, messages you send, and reviews you leave.
Device: a push-notification token, so we can notify you about orders and messages.

2. How we use it
To operate the marketplace — showing you relevant products/sellers, processing orders, enabling chat, and verifying sellers. To keep the platform safe — investigating reports, enforcing our Terms, and preventing fraud. To communicate with you — order updates, account notices, and (if you opt in) product updates.

3. Who we share it with
Other users see what you'd expect from a marketplace: your name and photo on your reviews and messages, your shop details if you're a seller. We do not sell your personal data. We share data with service providers who help us operate Sokoni (e.g. hosting, push notifications), under confidentiality obligations, and with authorities when legally required.

4. Your identity documents
NIDA numbers and ID photos are collected solely to verify that a seller is a real, identifiable person or business, as a defence against fraudulent shops. This data is visible only to our verification team and is never exposed through the app's public API or shown to other users.

5. Your choices
You can decline location access at any time — Sokoni falls back to manual area selection. You can request a copy of your data or ask us to delete your account by contacting support@sokoni.co.tz. Deleting your account removes your personal data, though some records (like completed orders) may be retained where required by law.

6. Data retention
We retain your data for as long as your account is active, plus a reasonable period afterward for legal, accounting, and fraud-prevention purposes.

7. Children
Sokoni is not directed at children under 18. We do not knowingly collect data from children.

8. Changes to this policy
We may update this Privacy Policy from time to time. Material changes will require you to accept the new version before continuing to use Sokoni.

9. Contact
Questions about this Privacy Policy, or requests regarding your data, can be sent to support@sokoni.co.tz.
''';

const _swBody = '''
Sera ya Faragha — toleo $sokoniTermsVersion

Ilisasishwa mwisho: 2026

1. Tunachokusanya
Akaunti: jina, nambari ya simu au barua pepe, na picha ya wasifu, kutoka kwako moja kwa moja au kutoka Google/Apple ukijisajili kwa njia hiyo.
Uthibitisho wa muuzaji: namba ya NIDA, picha ya kitambulisho cha taifa, na leseni yako ya biashara — hutumika tu na timu yetu ya uthibitisho, haionyeshwi hadharani.
Mahali: mahali pa kifaa chako, kwa ridhaa yako, hutumika kuonyesha wauzaji na bidhaa za karibu. Unaweza kukataa na kuchagua mkoa/wilaya kwa mkono badala yake.
Matumizi: bidhaa unazotazama au kupenda, oda unazoweka, ujumbe unaotuma, na maoni unayotoa.
Kifaa: alama ya arifa za push, ili tuweze kukujulisha kuhusu oda na ujumbe.

2. Jinsi tunavyotumia
Kuendesha soko — kukuonyesha bidhaa/wauzaji husika, kuchakata oda, kuwezesha mazungumzo, na kuthibitisha wauzaji. Kudumisha usalama wa jukwaa — kuchunguza ripoti, kutekeleza Masharti yetu, na kuzuia udanganyifu. Kuwasiliana nawe — masasisho ya oda, taarifa za akaunti, na (ukikubali) masasisho ya bidhaa.

3. Tunashiriki na nani
Watumiaji wengine wanaona kile unachotarajia kutoka soko: jina lako na picha kwenye maoni na ujumbe wako, maelezo ya duka lako ikiwa wewe ni muuzaji. Hatuuzi taarifa zako binafsi. Tunashiriki taarifa na watoa huduma wanaotusaidia kuendesha Sokoni (mfano, uhifadhi, arifa za push), chini ya wajibu wa usiri, na na mamlaka pale inapohitajika kisheria.

4. Hati zako za utambulisho
Namba za NIDA na picha za kitambulisho hukusanywa tu kuthibitisha kuwa muuzaji ni mtu au biashara halisi, kama ulinzi dhidi ya maduka ya udanganyifu. Taarifa hii inaonekana tu kwa timu yetu ya uthibitisho na haionyeshwi kamwe kupitia API ya hadharani ya programu au kwa watumiaji wengine.

5. Chaguo zako
Unaweza kukataa ruhusa ya mahali wakati wowote — Sokoni itarudi kwenye uchaguzi wa eneo kwa mkono. Unaweza kuomba nakala ya taarifa zako au kutuomba tufute akaunti yako kwa kuwasiliana na support@sokoni.co.tz. Kufuta akaunti yako huondoa taarifa zako binafsi, ingawa baadhi ya rekodi (kama oda zilizokamilika) zinaweza kuhifadhiwa pale sheria inapohitaji.

6. Uhifadhi wa data
Tunahifadhi taarifa zako kwa muda akaunti yako inapokuwa hai, pamoja na muda wa kutosha baadaye kwa madhumuni ya kisheria, uhasibu, na kuzuia udanganyifu.

7. Watoto
Sokoni haikusudiwi kwa watoto chini ya miaka 18. Hatukusanyi taarifa za watoto kwa makusudi.

8. Mabadiliko ya sera hii
Tunaweza kusasisha Sera hii ya Faragha mara kwa mara. Mabadiliko makubwa yatahitaji ukubali toleo jipya kabla ya kuendelea kutumia Sokoni.

9. Mawasiliano
Maswali kuhusu Sera hii ya Faragha, au maombi kuhusu taarifa zako, yanaweza kutumwa kwa support@sokoni.co.tz.
''';
