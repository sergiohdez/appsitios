$(() => {
  const $role = $('#membership-role');
  const $siteGroup = $('#approver-sites');
  const $submit = $('#membership-submit');

  function updateApproverSites() {
    const isApprover = $role.val() === 'approver';
    $siteGroup.prop('hidden', !isApprover);
    $siteGroup.find('input[type="checkbox"]').prop('disabled', !isApprover);
    const hasSelectedSite = $siteGroup.find('input[type="checkbox"]:checked').length > 0;
    $submit.prop('disabled', isApprover && !hasSelectedSite);
  }

  $role.on('change', updateApproverSites);
  $siteGroup.on('change', 'input[type="checkbox"]', updateApproverSites);
  updateApproverSites();

  $('[data-digits-only]').on('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
  });

  const $password = $('#account-password');
  const $passwordConfirmation = $('#account-password-confirmation');

  function validatePasswordFields() {
    if ($password.length) {
      $password[0].setCustomValidity($password.val().length < 12
        ? 'La contraseña debe tener al menos 12 caracteres.'
        : '');
    }

    if ($passwordConfirmation.length) {
      $passwordConfirmation[0].setCustomValidity($passwordConfirmation.val() !== $password.val()
        ? 'La confirmación de la contraseña no coincide.'
        : '');
    }
  }

  $password.on('input', validatePasswordFields);
  $passwordConfirmation.on('input', validatePasswordFields);
  $('#password-setup-form').on('submit', validatePasswordFields);
});
