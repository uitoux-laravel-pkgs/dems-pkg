app.component('eyatraAgentClaimList', {
    templateUrl: eyatra_agent_claim_list_template_url,
    controller: function(HelperService, $rootScope, $scope, $location, $http) {
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        // console.log(self.hasPermission);
        var dataTable = $('#agent_claim_list').DataTable({
            stateSave: true,
            "dom": dom_structure,
            "language": {
                "search": "",
                "searchPlaceholder": "Search",
                "lengthMenu": "Rows Per Page _MENU_",
                "paginate": {
                    "next": '<i class="icon ion-ios-arrow-forward"></i>',
                    "previous": '<i class="icon ion-ios-arrow-back"></i>'
                },
            },
            pageLength: 10,
            processing: true,
            serverSide: true,
            paging: true,
            ordering: false,
            ajax: {
                url: laravel_routes['listEYatraAgentClaimList'],
                type: "GET",
                dataType: "json",
                data: function(d) {}
            },
            columns: [
                { data: 'action', searchable: false, class: 'action' },
                { data: 'date', name: 'ey_agent_claims.invoice_date', searchable: false },
                { data: 'number', name: 'ey_agent_claims.number', searchable: true },
                { data: 'agent_code', name: 'agents.code', searchable: true },
                { data: 'invoice_number', name: 'ey_agent_claims.invoice_number', searchable: true },
                { data: 'invoice_date', name: 'ey_agent_claims.invoice_date', searchable: true },
                { data: 'invoice_amount', name: 'ey_agent_claims.invoice_amount', searchable: true },
                { data: 'status', name: 'configs.name', searchable: true },
            ],
            rowCallback: function(row, data) {
                $(row).addClass('highlight-row');
            }
        });
        $('.dataTables_length select').select2();
        $('.page-header-content .display-inline-block .data-table-title').html('Agent Claims');
        $('.add_new_button').html(
            '<a href="#!/eyatra/agent/claim/add" type="button" class="btn btn-secondary">' +
            'Add New' +
            '</a>'
        );
        $scope.deleteAgentClaimconfirm = function($id) {
            $('#delete_agent_claim').val($id);
        }

        $scope.deleteAgentClaim = function() {
            var id = $('#delete_agent_claim').val();
            $http.get(
                eyatra_agent_claim_delete_data_url + '/' + id,
            ).then(function(response) {
                if (response.data.success) {
                    new Noty({
                        type: 'success',
                        layout: 'topRight',
                        text: 'Agent Claim Deleted Successfully',
                    }).show();
                    $('#agent_claim_list').DataTable().ajax.reload(function(json) {});
                    $location.path('/eyatra/agent/claim/list');
                    $scope.$apply();
                } else {
                    new Noty({
                        type: 'error',
                        layout: 'topRight',
                        text: 'Agent Claim not Deleted',
                    }).show();
                }
            });
        }
        $rootScope.loading = false;
    }
});
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------
app.component('eyatraAgentClaimForm', {
    templateUrl: eyatra_agent_claim_form_template_url,
    controller: function($http, $location, HelperService, $routeParams, $rootScope, $scope) {
        $form_data_url = typeof($routeParams.agent_claim_id) == 'undefined' ? eyatra_agent_claim_form_data_url : eyatra_agent_claim_form_data_url + '/' + $routeParams.agent_claim_id;
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        self.angular_routes = angular_routes;
        $http.get(
            $form_data_url
        ).then(function(response) {
            if (!response.data.success) {
                new Noty({
                    type: 'error',
                    layout: 'topRight',
                    text: response.data.message,
                }).show();
                $location.path('/eyatra/agent/claim/list')
                $scope.$apply()
                return;
            }

            self.agent_claim = response.data.agent_claim;
            self.booking_list = response.data.booking_list;
            self.action = response.data.action;
            booking_pivot = response.data.booking_pivot;
            booking_pivot_amt = response.data.booking_pivot_amt;
            self.invoice_date = response.data.invoice_date;
            self.attachment = response.data.attachment;
            console.log(self.attachment.name);
            if (self.action == 'Edit') {
                var total = 0;
                var i = 0;
                $.each(booking_pivot, function(key, value) {
                    total += parseFloat(booking_pivot_amt[key]);
                    i++;
                });
                self.total_amount = total;
                $("#amount").html(total.toFixed(2));
                $("#count").html(i);
                // self.selected_amount = total.toFixed(2);
                // self.booking_checked_count = i;
            } else {
                self.total_amount = 0;
                $("#amount").html(0);
                $("#count").html(0);
                // self.selected_amount = 0;
                // self.booking_checked_count = 0;
            } // self.extras = response.data.extras;
            $rootScope.loading = false;

        });

        $scope.bookingChecked = function(id) {
            var value = booking_pivot.indexOf(id);
            return value;
        }

        $scope.checkedcount = function(id, amount) {
            if (event.target.checked == true) {
                var data = $(".booking_list:checked").length;
                self.total_amount += parseFloat(amount);
            } else {
                var data = $(".booking_list:checked").length;
                self.total_amount -= parseFloat(amount);
            }
            $("#amount").html(self.total_amount.toFixed(2));
            $("#count").html(data);
            // self.selected_amount =
            // self.booking_checked_count = data;
        }

        $scope.checkedallcount = function(id, amount) {
            console.log(id, amount);
        }

        $('#head_booking').on('click', function() {
            var total_amount = 0;
            var count = 0;
            var amount = 0;
            if (event.target.checked == true) {
                $('.booking_list').prop('checked', true);
                $.each($('.booking_list:checked'), function() {
                    count++;
                    amount += parseFloat($(this).attr('data-amount'));
                });
            } else {
                $('.booking_list').prop('checked', false);
                $.each($('.booking_list:checked'), function() {
                    count++;
                    amount += parseFloat($(this).attr('data-amount'));
                });
                self.total_amount = 0;
            }
            $("#amount").html(amount.toFixed(2));
            $("#count").html(count);
        });

        var form_id = '#agent-claim-form';

        $.validator.addMethod('decimal', function(value, element) {
            return this.optional(element) || /^((\d+(\\.\d{0,2})?)|((\d*(\.\d{1,2}))))$/.test(value);
        }, "Please enter a correct number, format 0.00");

        var v = jQuery(form_id).validate({
            errorPlacement: function(error, element) {
                if (element.attr('name') == 'booking_list[]') {
                    error.appendTo($('.booking_list_error'));
                } else if (element.attr('name') == 'date') {
                    error.appendTo($('.date_error'));
                } else {
                    error.insertAfter(element)
                }
            },
            ignore: '',
            rules: {
                'number': {
                    required: true,
                    minlength: 3,
                    maxlength: 191,
                },
                'date': {
                    required: true,
                },
                'amount': {
                    required: true,
                    number: true,
                    decimal: true,
                    //range: [0, 10000000],
                    maxlength: 11,
                },
                // 'invoice_attachmet': {
                //     extension: "docx|rtf|doc|pdf",
                // },
                'booking_list[]': {
                    required: true,
                },
            },
            messages: {
                'number': {
                    required: 'Agent Claim Number is Required',
                    minlength: 'Please enter minimum of 3 letters',
                    maxlength: 'Please enter maximum of 191 letters',
                },
                'date': {
                    required: 'Date is Required',
                },
                'amount': {
                    required: 'Amount is Required',
                    number: 'Enter Numbers Only',
                    decimal: 'Please enter a correct number, format 0.00',
                    // range: 'Enter Maximum 10000000 amount',
                    maxlength: 'Enter Maximum 10 Digit Number',

                },
                // 'invoice_attachmet': {
                //     extension: 'Select valied input file format LIKE: docx|rtf|doc|pdf',
                // },
                'booking_list[]': {
                    required: 'Booking list is Required',
                },
            },
            submitHandler: function(form) {

                let formData = new FormData($(form_id)[0]);
                $('#submit').button('loading');
                $.ajax({
                        url: laravel_routes['saveEYatraAgentClaim'],
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                    })
                    .done(function(res) {
                        if (!res.success) {
                            $('#submit').button('reset');
                            var errors = '';
                            for (var i in res.errors) {
                                errors += '<li>' + res.errors[i] + '</li>';
                            }
                            custom_noty('error', errors);
                        } else {
                            new Noty({
                                type: 'success',
                                layout: 'topRight',
                                text: res.message,
                            }).show();
                            $location.path('/eyatra/agent/claim/list')
                            $scope.$apply()
                        }
                    })
                    .fail(function(xhr) {
                        $('#submit').button('reset');
                        custom_noty('error', 'Something went wrong at server');
                    });
            },
        });

    }
});
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------