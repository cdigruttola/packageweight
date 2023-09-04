/**
 * Copyright since 2007 Carmine Di Gruttola
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    cdigruttola <c.digruttola@hotmail.it>
 *  @copyright Copyright since 2007 Carmine Di Gruttola
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
function displayRangeType() {
    if ($('input[name="shipping_method"]:checked').val() == 1) {
        string = string_weight;
        $('.weight_unit').show();
        $('.price_unit').hide();
        $('.package_weight').show();
    } else {
        string = string_price;
        $('.price_unit').show();
        $('.weight_unit').hide();
        $('.package_weight').hide();
    }
    is_freeClick($('input[name="is_free"]:checked'));
    $('.range_type').html(string);
}

function add_new_range() {
    if (!$('tr.fees_all td:last').hasClass('validated')) {
        alert(need_to_validate);
        return false;
    }

    last_sup_val = $('tr.range_sup td:last input').val();
    //add new rand sup input
    $('tr.range_sup td:last').after('<td class="range_data"><div class="input-group fixed-width-md"><span class="input-group-addon weight_unit" style="display: none;">' + PS_WEIGHT_UNIT + '</span><span class="input-group-addon price_unit" style="display: none;">' + currency_sign + '</span><input class="form-control" name="range_sup[]" type="text" autocomplete="off" /></div></td>');
    //add new rand inf input
    $('tr.range_inf td:last').after('<td class="border_bottom"><div class="input-group fixed-width-md"><span class="input-group-addon weight_unit" style="display: none;">' + PS_WEIGHT_UNIT + '</span><span class="input-group-addon price_unit" style="display: none;">' + currency_sign + '</span><input class="form-control" name="range_inf[]" type="text" value="' + last_sup_val + '" autocomplete="off" /></div></td>');
    $('tr.fees_all td:last').after('<td class="border_top border_bottom"><div class="input-group fixed-width-md"><span class="input-group-addon currency_sign" style="display:none" >' + currency_sign + '</span><input class="form-control" style="display:none" type="text" /></div></td>');

    $('tr.fees').each(function () {
        $(this).find('td:last').after('<td><div class="input-group fixed-width-md"><span class="input-group-addon currency_sign">' + currency_sign + '</span><input class="form-control" disabled="disabled" name="fees[' + $(this).data('zoneid') + '][]" type="text" /></div></td>');
    });
    $('tr.package_weight').each(function () {
        $(this).find('td:last').after('<td><div class="input-group fixed-width-md"><span class="input-group-addon weight_unit" style="display: none;">' + PS_WEIGHT_UNIT + '</span><input type="text" class="form-control" name="package_weight[]" type="text" /></div></td>');
    });
    $('tr.delete_range td:last').after('<td><button class="btn btn-default">' + labelDelete + '</button</td>');

    bind_inputs();
    rebuildTabindex();
    displayRangeType();
    return false;
}

function bind_inputs() {
    $('input').focus(function () {
        $(this).closest('div.input-group').removeClass('has-error');
        $('#carrier_wizard .actionBar a.btn').removeClass('disabled');
        $('.wizard_error').fadeOut('fast', function () {
            $(this).remove()
        });
    });

    $('tr.delete_range td button').off('click').on('click', function () {
        if (confirm(delete_range_confirm)) {
            index = $(this).closest('td').index();
            $('tr.range_sup td:eq(' + index + '), tr.range_inf td:eq(' + index + '), tr.fees_all td:eq(' + index + '), tr.delete_range td:eq(' + index + ')').remove();
            $('tr.fees').each(function () {
                $(this).find('td:eq(' + index + ')').remove();
            });
            $('tr.package_weight').each(function () {
                $(this).find('td:eq(' + index + ')').remove();
            });
            rebuildTabindex();
        }
        return false;
    });

    $('tr.fees td input:checkbox').off('change').on('change', function () {
        if ($(this).is(':checked')) {
            $(this).closest('tr').find('td').each(function () {
                index = $(this).index();
                if ($('tr.fees_all td:eq(' + index + ')').hasClass('validated')) {
                    enableGlobalFees(index);
                    $(this).find('div.input-group input:text').prop('disabled', false);
                } else
                    disabledGlobalFees(index);
            });
        } else
            $(this).closest('tr').find('td').find('div.input-group input:text').prop('disabled', true);

        return false;
    });

    $('tr.range_sup td input:text, tr.range_inf td input:text').keypress(function (evn) {
        index = $(this).closest('td').index();
        if (evn.keyCode == 13) {
            if (validateRange(index))
                enableRange(index);
            else
                disableRange(index);
            return false;
        }
    });

    $('tr.fees_all td input:text').keypress(function (evn) {
        index = $(this).parent('td').index();
        if (evn.keyCode == 13)
            return false;
    });

    $(document.body).off('change', 'tr.fees_all td input').on('change', 'tr.fees_all td input', function () {
        index = $(this).closest('td').index();
        val = $(this).val();
        $(this).val('');
        $('tr.fees').each(function () {
            $(this).find('td:eq(' + index + ') input:text:enabled').val(val);
        });

        return false;
    });

    $('input[name="is_free"]').off('click').on('click', function () {
        is_freeClick(this);
    });

    $('input[name="shipping_method"]').off('click').on('click', function () {
        $.ajax({
            type: "POST",
            url: validate_url,
            async: false,
            dataType: 'html',
            data: 'id_carrier=' + parseInt($('#id_carrier').val()) + '&shipping_method=' + parseInt($(this).val()) + '&action=changeRanges&ajax=1',
            success: function (data) {
                $('#zone_ranges').replaceWith(data);
                displayRangeType();
                bind_inputs();
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                jAlert("TECHNICAL ERROR: \n\nDetails:\nError thrown: " + XMLHttpRequest + "\n" + 'Text status: ' + textStatus);
            }
        });
    });

    $('#zones_table td input[type=text]').off('change').on('change', function () {
        checkAllFieldIsNumeric();
    });
}
