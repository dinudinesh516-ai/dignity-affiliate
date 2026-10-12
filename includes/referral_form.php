<?php
/**
 * Shared referral form fields.
 * $formMode: 'client' (client fills own details via QR) | 'agent' (agent fills for client) | 'admin'
 */
$formMode = $formMode ?? 'client';
$you = $formMode === 'client';
$selInterests = (array)($_POST['interests'] ?? []);
$pref = $_POST['preferred_contact'] ?? 'whatsapp';
?>
<div class="mb-3">
  <label class="form-label" for="full_name"><?= $you ? 'Your full name' : 'Client full name' ?></label>
  <input class="form-control" id="full_name" name="full_name" value="<?= e(old('full_name')) ?>" autocomplete="<?= $you ? 'name' : 'off' ?>" required>
</div>
<div class="mb-3">
  <label class="form-label" for="phone"><?= $you ? 'Phone / WhatsApp' : 'Client phone / WhatsApp' ?></label>
  <?= phone_input('phone', old('phone'), ['required' => true, 'autocomplete' => $you ? 'tel-national' : 'off']) ?>
  <div class="dup-msg" data-dup-check="<?= e($formMode) ?>" data-dup-url="<?= e(url('check-phone.php')) ?>" aria-live="polite"></div>
</div>
<div class="mb-3">
  <label class="form-label" for="email"><?= $you ? 'Email' : 'Client email' ?> <span class="text-muted fw-normal">(optional)</span></label>
  <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="<?= $you ? 'email' : 'off' ?>" autocapitalize="none">
</div>
<div class="mb-2">
  <label class="form-label d-block"><?= $you ? 'What are you looking for?' : 'What is the client looking for?' ?></label>
  <?php foreach (INTEREST_OPTIONS as $opt): ?>
    <label class="chip-check"><input type="checkbox" name="interests[]" value="<?= e($opt) ?>" <?= in_array($opt, $selInterests, true) ? 'checked' : '' ?>><span><?= $opt === 'Other' ? 'Other <small class="opacity-75">(please specify in notes)</small>' : e($opt) ?></span></label>
  <?php endforeach; ?>
</div>
<div class="mb-3">
  <label class="form-label" for="looking_for">Note <span class="text-muted fw-normal">(details, style, occasion, timeline…)</span></label>
  <textarea class="form-control" id="looking_for" name="looking_for" rows="3" maxlength="2000" placeholder="<?= $you ? 'e.g. Looking for a 2ct oval engagement ring in platinum for a proposal in June' : 'e.g. Client wants a custom emerald necklace for her anniversary' ?>"><?= e(old('looking_for')) ?></textarea>
</div>
<div class="mb-3">
  <label class="form-label" for="budget">Approximate budget <span class="text-muted fw-normal">(optional)</span></label>
  <select class="form-select" id="budget" name="budget">
    <?php foreach (['' => 'Prefer not to say', 'Under $2,500' => 'Under $2,500', '$2,500 – $5,000' => '$2,500 – $5,000', '$5,000 – $10,000' => '$5,000 – $10,000', '$10,000 – $25,000' => '$10,000 – $25,000', '$25,000+' => '$25,000+'] as $v => $l): ?>
      <option value="<?= e($v) ?>" <?= old('budget') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="mb-3">
  <label class="form-label d-block">Best way to contact <?= $you ? 'you' : 'the client' ?></label>
  <div class="seg">
    <?php foreach (['whatsapp' => 'WhatsApp', 'call' => 'Call', 'email' => 'Email'] as $v => $l): ?>
      <label><input type="radio" name="preferred_contact" value="<?= $v ?>" <?= $pref === $v ? 'checked' : '' ?>><span><?= $l ?></span></label>
    <?php endforeach; ?>
  </div>
</div>
