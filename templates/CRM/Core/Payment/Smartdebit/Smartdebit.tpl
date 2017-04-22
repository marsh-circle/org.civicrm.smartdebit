<div><span style="float: right;margin: 25px;"><img src="{crmResURL ext=uk.co.vedaconsulting.payment.ukdirectdebit file=images/direct_debit_small.png}" alt="Direct Debit Logo" border="0"></span></div>
<div style="clear: both;"></div>
{ts}<p>All the normal Direct Debit safeguards and guarantees apply.
  No changes in the amount, date or frequency to be debited can be made without notifying you at least 10 working days in advance of your account being debited.
  In the event of any error, you are entitled to an immediate refund from your bank or building society.
  You have the right to cancel a Direct Debit Instruction at any time simply by writing to your bank or building society, with a copy to us.</p>
  <p>In order to set up your Direct Debit Instruction on-line you will need to provide the following information through the setting up procedure (your cheque book contains all the bank details that you require):</p>
  <p>Bank or Building Society name and account number, sort code and branch address.</p>
  <ul>
    <li>If you are not the account holder, a paper Direct Debit Instruction will be sent for completion. Please click to end</li>
    <li>If this is a personal account continue with the set-up procedure</li>
    <li>If it is a business account and more than one person is required to authorise debits on this account, a paper Direct Debit Instruction will be sent to the Payers for completion.</li>
  </ul>

  <p>Alternatively you can print off your on-screen Direct Debit Instruction and post it to us: <b>{$company_address.company_name}</b>, {if ($company_address.address1 != '')} {$company_address.address1}, {/if}{if ($company_address.address2 != '')} {$company_address.address2}, {/if}{if ($company_address.address3 != '')} {$company_address.address3}, {/if}{if ($company_address.address4 != '')} {$company_address.address4}, {/if}{if ($company_address.town != '')} {$company_address.town}, {/if}{if ($company_address.county != '')} {$company_address.county}, {/if}{if ($company_address.postcode != '')} {$company_address.postcode}{/if}. If you are unable to print please contact us on {$telephoneNumber} (tel no) and we will post you a paper Direct Debit Instruction.
    If you do not wish to proceed any further please <a href="/">click here</a> to end.</p>
  <p>The details of your Direct Debit Instruction will be sent to you within 3 working days or no later than 10 working days before the first collection.</p>{/ts}

{literal}
  <style type="text/css">
    #multiple_block > input {
      border: 1px solid;
      min-width: inherit !important;
      width: 43px;
    }
  </style>
  <script type="text/javascript">
      CRM.$('#payment_information').ready(function() {
          if(cj('tr').attr('id') !== "multiple_block") {

              cj("#bank_identification_number").parent().prepend('<div id ="multiple_block"></div>');
              cj("#multiple_block")

                  .html('<input type = "text" size = "3" maxlength = "2" name = "block_1" id = "block_1"/>'
                      +' - <input type = "text" size = "3" maxlength = "2" name = "block_2" id ="block_2"/>'
                      +' - <input type = "text" size = "3" maxlength = "2" name = "block_3" id = "block_3"/>');

              cj('#block_1').change(function() {
                  cj.fn.myFunction();
              });

              cj('#block_2').change(function() {
                  cj.fn.myFunction();
              });

              cj('#block_3').change(function() {
                  cj.fn.myFunction();
              });

              //function to get value of new title boxes and concatenate the values and display in mailing_title
              cj.fn.myFunction = function() {
                  var field1 = cj("input#block_1").val();
                  var field2 = cj("input#block_2").val();
                  var field3 = cj("input#block_3").val();
                  var finalFieldValue = field1 + field2 + field3;

                  cj('input#bank_identification_number').val(finalFieldValue);
              };

              //hide the mailing title
              cj("#bank_identification_number").hide();

              //split the value of mailing_title
              //make it to appear on the new three title boxes
              var fieldValue = cj("#bank_identification_number").val();

              var fieldLength;
              if ( fieldValue !== undefined ) {
                  fieldLength = fieldValue.length;
              } else {
                  fieldLength = 0;
              }

              if (fieldLength !== 0) {

                  var fieldSplit = (fieldValue+'').split('');

                  cj('#block_1').val(fieldSplit[0]+fieldSplit[1]);

                  if(!(fieldSplit[0]+fieldSplit[1])) {
                      cj('#block_1').val("");
                  }

                  cj('#block_2').val(fieldSplit[2]+fieldSplit[3]);

                  if(!(fieldSplit[2]+fieldSplit[3])) {
                      cj('#block_2').val("");
                  }

                  cj('#block_3').val(fieldSplit[4]+fieldSplit[5]);

                  if(!(fieldSplit[4]+fieldSplit[5])) {
                      cj('#block_3').val("");
                  }

              }
          }
      });
  </script>
{/literal}
