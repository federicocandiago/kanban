<?php
/** Privacy Policy — information under Articles 13 and 14 of Regulation (EU) 2016/679 (GDPR). */
$app = (string) env('APP_NAME');
$email = legal('email');
$mail = '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
?>
<article class="legal mx-auto">
    <h1 class="h3 mb-2"><?= e(__('Privacy Policy')) ?></h1>
    <p class="text-secondary small mb-5"><?= e(__('Last updated: :date', ['date' => legal('date')])) ?></p>

    <p><?= e(__('This policy explains how :app processes your personal data, in accordance with Regulation (EU) 2016/679 (General Data Protection Regulation, "GDPR") and the applicable national law. How we use cookies is described in the separate Cookie Policy.', ['app' => $app])) ?></p>

    <h2>1. <?= e(__('Data controller')) ?></h2>
    <p>
        <?= e(__('The data controller is:')) ?><br>
        <strong><?= e(legal('controller')) ?></strong><?php if (legal('address')): ?><br><?= e(legal('address')) ?><?php endif ?><br>
        <?= e(__('Email')) ?>: <?= $mail ?>
    </p>
    <p><?= e(__('You can write to this address for any question about your personal data and to exercise your rights.')) ?></p>

    <h2>2. <?= e(__('Personal data we process')) ?></h2>
    <ul>
        <li><strong><?= e(__('Account data')) ?>:</strong> <?= e(__('name, email address and password. The password is never stored in readable form: we only keep a one-way hash of it.')) ?></li>
        <li><strong><?= e(__('Profile data you choose to provide')) ?>:</strong> <?= e(__('profile picture (re-encoded by our system, which removes any embedded metadata such as location), preferred language and theme.')) ?></li>
        <li><strong><?= e(__('Service data')) ?>:</strong> <?= e(__('your boards, lists, cards (names, descriptions, dates, labels), the files you attach, and the change log of each card, which records who changed what and when. What you write on a shared board is visible to its members.')) ?></li>
        <li><strong><?= e(__('Activity data')) ?>:</strong> <?= e(__('date of registration, last sign-in, the boards you are a member of and when you last opened them, notifications.')) ?></li>
        <li><strong><?= e(__('Calendar, if you connect one')) ?>:</strong> <?= e(__('the address of your calendar server, your username and app-specific password (stored encrypted, used only to read and write your events), and a copy of the events of the months you open (titles, descriptions, dates, sub-calendars, attachment names), kept to show them quickly and refreshed from your calendar. Disconnecting the calendar deletes all of it from our server.')) ?></li>
        <li><strong><?= e(__('Data of the people you invite')) ?>:</strong> <?= e(__('when you share a board with an email address that has no account, we keep that address and send it one invitation email. The invitation is deleted with the board; the person can ignore it or ask us to delete it.')) ?></li>
        <li><strong><?= e(__('API token, if you turn the API on')) ?>:</strong> <?= e(__('a one-way hash of the token (never the token itself), what it can do, when it was created, when it expires and when it was last used. Turning API access off deletes it.')) ?></li>
        <li><strong><?= e(__('Security counters')) ?>:</strong> <?= e(__('to stop password guessing and abuse we count, for a short time, failed sign-ins and some requests per account. The counters are kept under a one-way hash of the account name, never with IP addresses, and are deleted when they expire.')) ?></li>
        <li><strong><?= e(__('Technical data')) ?>:</strong> <?= e(__('the web server may record standard access logs (IP address, date and time, requested page, browser type) for security purposes. The application itself does not store your IP address.')) ?></li>
    </ul>
    <p><?= e(__('We do not ask for, and ask you not to upload, special categories of data (Article 9 GDPR) or data about criminal convictions. The content of your boards is your responsibility: do not store other people\'s personal data in it without a valid legal basis.')) ?></p>

    <h2>3. <?= e(__('Purposes and legal bases')) ?></h2>
    <div class="table-responsive surface mb-3">
        <table class="table small mb-0">
            <thead><tr><th><?= e(__('Purpose')) ?></th><th><?= e(__('Legal basis')) ?></th></tr></thead>
            <tbody>
                <tr><td><?= e(__('Creating and managing your account, signing you in, providing your boards and sharing them with the people you choose.')) ?></td>
                    <td><?= e(__('Performance of a contract (Art. 6(1)(b) GDPR): the Terms of Service you accept when signing up.')) ?></td></tr>
                <tr><td><?= e(__('Security of the service, preventing abuse, enforcing the Terms of Service (e.g. pausing an account).')) ?></td>
                    <td><?= e(__('Legitimate interest of the controller (Art. 6(1)(f) GDPR) in protecting the service and its users.')) ?></td></tr>
                <tr><td><?= e(__('Complying with legal obligations and requests from authorities.')) ?></td>
                    <td><?= e(__('Legal obligation (Art. 6(1)(c) GDPR).')) ?></td></tr>
                <tr><td><?= e(__('Defending our rights in legal proceedings.')) ?></td>
                    <td><?= e(__('Legitimate interest of the controller (Art. 6(1)(f) GDPR).')) ?></td></tr>
            </tbody>
        </table>
    </div>
    <p><?= e(__('We do not use your data for marketing, we do not sell it, and we do not carry out profiling or decisions based solely on automated processing within the meaning of Article 22 GDPR.')) ?></p>

    <h2>4. <?= e(__('Is providing data mandatory?')) ?></h2>
    <p><?= e(__('Name, email address and password are necessary to create an account: without them we cannot provide the service. Profile picture and everything else are optional.')) ?></p>

    <h2>5. <?= e(__('Who can access your data')) ?></h2>
    <ul>
        <li><?= e(__('The members of the boards you share: they see the board, its cards and its log, with your name and profile picture.')) ?></li>
        <li><?= e(__('The administrators of the service, authorised and bound to confidentiality, to the extent needed to run it. They can see account data and how many boards each account has; they do not open your boards unless this is necessary for security, to comply with the law, or at your request.')) ?></li>
        <li><?= e(__('Any provider hosting our servers, acting as data processor under a contract pursuant to Article 28 GDPR.')) ?></li>
        <li><?= e(__('Public authorities, only when required by law.')) ?></li>
    </ul>
    <p><?= e(__('Your data is not disclosed to anyone else and is never published.')) ?></p>
    <p><?= e(__('If you connect a calendar, our server exchanges your events with the calendar service you chose (for example Apple iCloud), which processes them under its own privacy policy.')) ?></p>

    <h2>6. <?= e(__('Where your data is stored')) ?></h2>
    <p><?= e(__('Data is stored on our own servers, located in :location. The service does not use analytics or advertising. The only third-party service is Unsplash, for board background photos: when you search photos our server sends Unsplash only the search words, and the photos are then loaded by your browser from Unsplash (see the Cookie Policy). If data is ever transferred outside the European Economic Area, this will only happen on the basis of an adequacy decision or appropriate safeguards such as the Standard Contractual Clauses (Articles 45–46 GDPR).', ['location' => legal('location')])) ?></p>

    <h2>7. <?= e(__('How long we keep it')) ?></h2>
    <ul>
        <li><?= e(__('Account, profile and service data: as long as your account exists. When you delete your account (or an administrator deletes it), your data, your profile picture and the boards you own are deleted immediately; on boards owned by others your cards stay, and the log keeps your name.')) ?></li>
        <li><?= e(__('Archived boards, lists and cards are kept until they are restored or deleted for good; deleted ones, with their attachments, are removed immediately.')) ?></li>
        <li><?= e(__('Web server access logs: at most :days days, unless needed to investigate a specific security incident.', ['days' => legal('log_days')])) ?></li>
        <li><?= e(__('Session data: until you sign out or the browser session ends.')) ?></li>
        <li><?= e(__('Server backups, if any, are overwritten in the normal backup cycle.')) ?></li>
    </ul>

    <h2>8. <?= e(__('Your rights')) ?></h2>
    <p><?= e(__('Under Articles 15–22 GDPR you have the right to:')) ?></p>
    <ul>
        <li><strong><?= e(__('Access')) ?></strong> — <?= e(__('obtain confirmation of whether we process your data and a copy of it;')) ?></li>
        <li><strong><?= e(__('Rectification')) ?></strong> — <?= e(__('correct inaccurate data (you can change your name and preferences from "My account");')) ?></li>
        <li><strong><?= e(__('Erasure')) ?></strong> — <?= e(__('have your data deleted (you can delete your account yourself from "My account");')) ?></li>
        <li><strong><?= e(__('Restriction')) ?></strong> — <?= e(__('ask us to limit the processing in the cases provided by Article 18;')) ?></li>
        <li><strong><?= e(__('Portability')) ?></strong> — <?= e(__('receive your data in a structured, commonly used, machine-readable format (ask us at the address above);')) ?></li>
        <li><strong><?= e(__('Objection')) ?></strong> — <?= e(__('object at any time to processing based on our legitimate interest, for reasons related to your particular situation.')) ?></li>
    </ul>
    <p><?= __('To exercise your rights write to :mail. We will reply without undue delay and in any case within one month, which may be extended by two further months for complex requests (Article 12 GDPR). We may ask you to confirm your identity.', ['mail' => $mail]) ?></p>
    <p><?= e(__('You also have the right to lodge a complaint with a supervisory authority, in particular in the Member State where you live or work. In Italy it is the Garante per la protezione dei dati personali (www.garanteprivacy.it).')) ?></p>

    <h2>9. <?= e(__('Security')) ?></h2>
    <p><?= e(__('We adopt technical and organisational measures appropriate to the risk (Article 32 GDPR), including: hashed passwords, protection against cross-site request forgery, session cookies not accessible to scripts, access control on every board and attachment, re-encoding of uploaded images, and separation between the data of different users. No system is perfectly secure: keep your password private and a copy of important files.')) ?></p>
    <p><?= e(__('In the event of a personal data breach we will notify the supervisory authority and, where required, the people concerned, as provided by Articles 33 and 34 GDPR.')) ?></p>

    <h2>10. <?= e(__('Minors')) ?></h2>
    <p><?= e(__('The service is not intended for children under 14 (or the higher age of digital consent required in your country). If you believe a minor has registered without authorisation, please contact us and we will delete the account.')) ?></p>

    <h2>11. <?= e(__('Changes to this policy')) ?></h2>
    <p><?= e(__('We may update this policy, for example when the service changes. The date of the last update is shown at the top of the page; significant changes will be announced on the site.')) ?></p>
</article>
