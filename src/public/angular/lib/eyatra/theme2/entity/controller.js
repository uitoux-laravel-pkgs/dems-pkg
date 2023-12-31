app.component('eyatraEntityList', {
    templateUrl: eyatra_entity_list_template_url,
    controller: function(HelperService, $http, $rootScope, $scope, $routeParams) {
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        var dataTable;
        var id = '';
        var add_url = '#!/entity/add/' + self.id;
        var title = '';
        $http.get(
            eyatra_entity_list_data_url + '/' + $routeParams.entity_type_id
        ).then(function(response) {

            self.id = response.data.entity_type.id;
            self.title = response.data.entity_type.name;
            var dataTable = $('#entity_table').DataTable({
                stateSave: true,
                "dom": dom_structure_separate,
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
                    url: eyatra_entity_get_list_url,
                    type: "GET",
                    dataType: "json",
                    data: function(d) {
                        d.entity_type_id = $routeParams.entity_type_id;
                    },
                },
                columns: [
                    { data: 'action', searchable: false, class: 'action' },
                    { data: 'name', name: 'entities.name', searchable: true },
                    { data: 'created_by', name: 'users.username', searchable: false },
                    { data: 'updated_by', name: 'updater.username', searchable: false },
                    { data: 'deleted_by', name: 'deactivator.username', searchable: false },
                    { data: 'created_at', name: 'entities.created_at', searchable: false },
                    { data: 'updated_at1', name: 'entities.updated_at', searchable: false },
                    { data: 'deleted_at', name: 'entities.deleted_at', searchable: false },
                    { data: 'status', searchable: false },
                ],
                rowCallback: function(row, data) {
                    $(row).addClass('highlight-row');
                }

            });
            $('.dataTables_length select').select2();
            $('.separate-page-header-content .data-table-title').html('<p class="breadcrumb">Masters / ' + response.data.entity_type.name + '</p><h3 class="title">' + response.data.entity_type.name + '</h3>');
            var add_url = '#!/entity/add/' + self.id;
            if (self.id) {
                if ($routeParams.entity_type_id == '501') {
                    $rootScope.title = 'Trip Purpose';
                    self.trip_add_permission = self.hasPermission('eyatra-travel-purposes-add');
                    console.log(self.trip_add_permission);
                    if (self.trip_add_permission) {
                        //alert('test');
                        $('.add_new_button').html(
                            '<a href=' + add_url + ' type="button" class="btn btn-secondary ">' +
                            'Add New' +
                            '</a>'
                        );
                    }
                } else if ($routeParams.entity_type_id == '503') {
                    $rootScope.title = 'Other Expense';
                    self.other_add_permission = self.hasPermission('eyatra-local-travel-modes-add');
                    console.log(self.other_add_permission);
                    if (self.other_add_permission) {
                        $('.add_new_button').html(
                            '<a href=' + add_url + ' type="button" class="btn btn-secondary ">' +
                            'Add New' +
                            '</a>'
                        );
                    }
                } else if ($routeParams.entity_type_id == '506') {
                    $rootScope.title = 'City Category';
                    self.city_category_add_permission = self.hasPermission('eyatra-category-add');
                    if (self.city_category_add_permission) {
                        $('.add_new_button').html(
                            '<a href=' + add_url + ' type="button" class="btn btn-secondary ">' +
                            'Add New' +
                            '</a>'
                        );
                    }
                } else if ($routeParams.entity_type_id == '512') {
                    $rootScope.title = 'Expense Types';
                    self.petty_cash_expense_add_permission = self.hasPermission('eyatra-pettycash-expense-types');
                    if (self.petty_cash_expense_add_permission) {
                        $('.add_new_button').html(
                            '<a href=' + add_url + ' type="button" class="btn btn-secondary ">' +
                            'Add New' +
                            '</a>'
                        );
                    }
                }
                /*   $('.add_new_button').html(
                '<a href=' + add_url + ' type="button" class="btn btn-secondary ">' +
                'Add New' +
                '</a>'
                );*/
            }

            setTimeout(function() {
                var x = $('.separate-page-header-inner.search .custom-filter').position();
                var d = document.getElementById('entity_table');
                x.left = x.left + 15;
                d.style.left = x.left + 'px';
            }, 500);

            $scope.deleteEntityDetail = function($id) {
                $('#del').val($id);
            }
            $scope.deleteEntity = function() {

                $id = $('#del').val();
                $http.get(
                    eyatra_entity_delete_url + '/' + $id,
                ).then(function(response) {
                    console.log(response.data);
                    if (response.data.success) {

                        $noty = new Noty({
                            type: 'success',
                            layout: 'topRight',
                            text: 'Entity Detail Deleted Successfully',
                            animation: {
                                speed: 500 // unavailable - no need
                            },
                        }).show();
                        setTimeout(function() {
                            $noty.close();
                        }, 5000);
                    }
                    dataTable.ajax.reload(function(json) {});

                });
            }
        });


        $rootScope.loading = false;
    }
});
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------

app.component('eyatraEntityForm', {
    templateUrl: eyatra_entity_form_template_url,
    controller: function($http, $location, $location, HelperService, $routeParams, $rootScope, $scope) {
        $form_data_url = typeof($routeParams.entity_id) == 'undefined' ? eyatra_entity_form_data_url + '/' + $routeParams.entity_type_id : eyatra_entity_form_data_url + '/' + $routeParams.entity_type_id + '/' + $routeParams.entity_id;
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        self.angular_routes = angular_routes;
        $http.get(
            $form_data_url,
        ).then(function(response) {
            //console.log(response.data);
            if (!response.data.success) {
                $noty = new Noty({
                    type: 'error',
                    layout: 'topRight',
                    text: response.data.error,
                    animation: {
                        speed: 500 // unavailable - no need
                    },
                }).show();
                setTimeout(function() {
                    $noty.close();
                }, 5000);
                $location.path('/entity/list' + '/' + $routeParams.entity_type_id)
                $scope.$apply()
                return;
            }
            self.entity = response.data.entity;
            self.entity_type = response.data.entity_type;
            str = response.data.entity_type.name;
            if (str.substring(str.length - 1) == 's') {
                self.entity_name = str.substring(0, str.length - 1);

            } else {
                self.entity_name = str;

            }
            self.action = response.data.action;
            $rootScope.loading = false;

        });
        // $('input').on('blur keyup', function() {
        //     if ($("#entity_form").valid()) {
        //         $('#submit').prop('disabled', false);
        //     } else {
        //         $('#submit').prop('disabled', 'disabled');
        //     }
        // });
        // $('#submit').click(function() {
        //     if ($("#entity_form").valid()) {
        //         $('#submit').prop('disabled', false);
        //     } else {
        //         $('#submit').prop('disabled', 'disabled');
        //     }
        // });
        var form_id = form_ids = '#entity_form';
        var v = jQuery(form_ids).validate({
            errorPlacement: function(error, element) {
                if (element.hasClass("name")) {
                    error.appendTo($('.name_error'));
                } else {
                    error.insertAfter(element)
                }
            },
            ignore: '',
            rules: {
                'name': {
                    required: true,
                    minlength: 1,
                    maxlength: 191,
                },

            },
            messages: {
                'name': {
                    minlength: 'Please enter minimum of 3 characters',
                    maxlength: 'Please enter maximum of 191 characters',
                },
            },
            submitHandler: function(form) {
                let formData = new FormData($(form_id)[0]);
                $('#submit').button('loading');
                $.ajax({
                        url: eyatra_save_entity_url + '/' + $routeParams.entity_type_id,
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                    })
                    .done(function(res) {
                        console.log(res.success);
                        if (!res.success) {
                            $('#submit').button('reset');
                            // $('#submit').prop('disabled', 'disabled');
                            var errors = '';
                            for (var i in res.errors) {
                                errors += '<li>' + res.errors[i] + '</li>';
                            }
                            custom_noty('error', errors);
                        } else {
                            $noty = new Noty({
                                type: 'success',
                                layout: 'topRight',
                                text: 'Entity Details Added Successfully',
                                text: res.message,
                                animation: {
                                    speed: 500 // unavailable - no need
                                },
                            }).show();
                            setTimeout(function() {
                                $noty.close();
                            }, 5000);
                            $location.path('/entity/list' + '/' + $routeParams.entity_type_id)
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

app.component('eyatraEntityView', {
    templateUrl: eyatra_entity_view_template_url,

    controller: function($http, $location, $routeParams, HelperService, $scope) {
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        $http.get(
            eyatra_entity_view_url + '/' + $routeParams.entity_id
        ).then(function(response) {
            self.entity = response.data.entity;
        });
    }
});


//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------