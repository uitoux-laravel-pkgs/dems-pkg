app.component('eyatraTripClaimList', {
    templateUrl: eyatra_trip_claim_list_template_url,
    controller: function(HelperService, $rootScope, $scope, $http) {
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        $http.get(
            trip_claim_filter_data_url
        ).then(function(response) {
            console.log(response.data);
            self.employee_list = response.data.employee_list;
            self.purpose_list = response.data.purpose_list;
            self.trip_status_list = response.data.trip_status_list;
            $rootScope.loading = false;
        });

        var dataTable = $('#eyatra_trip_claim_list_table').DataTable({
            stateSave: true,
            "dom": dom_structure_separate_2,
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
                url: laravel_routes['listEYatraTripClaimList'],
                type: "GET",
                dataType: "json",
                data: function(d) {
                    // d.employee_id = $('#employee_id').val();
                    d.purpose_id = $('#purpose_id').val();
                    d.from_date = $('#from_date').val();
                    d.to_date = $('#to_date').val();
                    d.status_id = $('#status_id').val();
                }
            },
            columns: [
                { data: 'action', searchable: false, class: 'action' },
                { data: 'number', name: 'trips.number', searchable: true },
                { data: 'created_date', name: 'trips.created_date', searchable: false },
                { data: 'ecode', name: 'e.code', searchable: true },
                { data: 'ename', name: 'users.name', searchable: true },
                { data: 'travel_period', name: 'travel_period', searchable: false },
                { data: 'purpose', name: 'purpose.name', searchable: true },
                { data: 'advance_received', name: 'trips.advance_received', searchable: false },
                { data: 'status', name: 'status.name', searchable: true },
            ],
            rowCallback: function(row, data) {
                $(row).addClass('highlight-row');
            }
        });
        $('.dataTables_length select').select2();

        //CURRENT DATE SHOW IN DATEPICKER
        setTimeout(function() {
            $('div[data-provide="datepicker"]').datepicker({
                todayHighlight: true,
                autoclose: true,
            });
        }, 1000);

        setTimeout(function() {
            var x = $('.separate-page-header-inner.search .custom-filter').position();
            var d = document.getElementById('eyatra_trip_claim_list_table_filter');
            x.left = x.left + 15;
            d.style.left = x.left + 'px';
        }, 500);

        // $scope.getEmployeeData = function(query) {
        //     $('#employee_id').val(query);
        //     dataTable.draw();
        // }
        $scope.getPurposeData = function(query) {
            $('#purpose_id').val(query);
            dataTable.draw();
        }
        $scope.getFromDateData = function(query) {
            console.log(query);
            $('#from_date').val(query);
            dataTable.draw();
        }
        $scope.getToDateData = function(query) {
            console.log(query);
            $('#to_date').val(query);
            dataTable.draw();
        }
        $scope.getStatusData = function(query) {
            $('#status_id').val(query);
            dataTable.draw();
        }

        $scope.reset_filter = function(query) {
            // $('#employee_id').val(-1);
            $('#purpose_id').val(-1);
            $('#from_date').val('');
            $('#to_date').val('');
            $('#status_id').val(-1);
            dataTable.draw();
        }

        $scope.deleteTrip = function(id) {
            $('#del').val(id);
        }
        $scope.confirmDeleteTrip = function() {
            $id = $('#del').val();
            $http.get(
                eyatra_trip_claim_delete_url + '/' + $id,
            ).then(function(response) {
                if (!response.data.success) {
                    var errors = '';
                    for (var i in res.errors) {
                        errors += '<li>' + res.errors[i] + '</li>';
                    }
                    $noty = new Noty({
                        type: 'error',
                        layout: 'topRight',
                        text: errors,
                        animation: {
                            speed: 500 // unavailable - no need
                        },
                    }).show();
                    setTimeout(function() {
                        $noty.close();
                    }, 1000);
                } else {
                    $noty = new Noty({
                        type: 'success',
                        layout: 'topRight',
                        text: 'Trips Deleted Successfully',
                        animation: {
                            speed: 500 // unavailable - no need
                        },
                    }).show();
                    setTimeout(function() {
                        $noty.close();
                    }, 1000);
                    $('#delete_emp').modal('hide');
                    dataTable.ajax.reload(function(json) {});
                }
            });
        }
        $rootScope.loading = false;

    }
});
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------
app.component('eyatraTripClaimForm', {
    templateUrl: eyatra_trip_claim_form_template_url,
    controller: function($http, $location, HelperService, $routeParams, $rootScope, $element, $mdSelect, $scope, $timeout) {
        $form_data_url = typeof($routeParams.trip_id) == 'undefined' ? eyatra_trip_claim_form_data_url + '/' : eyatra_trip_claim_form_data_url + '/' + $routeParams.trip_id;
        var self = this;
        var lodgings_removal_id = [];
        var boardings_removal_id = [];
        var local_travels_removal_id = [];
        var arrival_date_error_flag = 0;
        $('.testt1').imageuploadify();
        $scope.searchTravelMode;
        $scope.clearSearchTravelMode = function() {
            $scope.searchTravelMode = '';
        };

        /* Modal Md Select Hide */
        $('.modal').bind('click', function(event) {
            if ($('.md-select-menu-container').hasClass('md-active')) {
                $mdSelect.hide();
            }
        });

        self.hasPermission = HelperService.hasPermission;
        self.angular_routes = angular_routes;
        self.eyatra_trip_claim_visit_attachment_url = eyatra_trip_claim_visit_attachment_url;
        self.eyatra_trip_claim_lodging_attachment_url = eyatra_trip_claim_lodging_attachment_url;
        self.eyatra_trip_claim_boarding_attachment_url = eyatra_trip_claim_boarding_attachment_url;
        self.eyatra_trip_claim_local_travel_attachment_url = eyatra_trip_claim_local_travel_attachment_url;
        self.enable_switch_tab = true;

        $http.get(
            $form_data_url
        ).then(function(response) {
            /*if (!response.data.success) {
                $noty = new Noty({
                    type: 'error',
                    layout: 'topRight',
                    text: response.data.error,
                }).show();
                setTimeout(function() {
                        $noty.close();
                    }, 1000);
                $location.path('/eyatra/trip/claim/list')
                $scope.$apply()
                return;
            }*/
            // console.log(response.data.trip);
            // console.log(response.data.travel_dates);
            // console.log(response.data.cities_with_expenses);
            self.cities_with_expenses = response.data.cities_with_expenses;
            self.employee = response.data.employee;
            self.gender = (response.data.employee.gender).toLowerCase();
            self.travel_cities = response.data.travel_cities;
            self.travel_dates = response.data.travel_dates;
            self.extras = response.data.extras;
            self.trip = response.data.trip;
            self.lodging_attachments = response.data.trip.lodging_attachments;
            self.boarding_attachments = response.data.trip.boarding_attachments;
            self.action = response.data.action;
            self.travelled_cities_with_dates = response.data.travelled_cities_with_dates;
            self.lodge_cities = response.data.lodge_cities;
            self.travel_dates_list = response.data.travel_dates_list;
            if (self.action == 'Add') {
                // self.trip.boardings = [];
                // self.trip.local_travels = [];
                self.is_deviation = 0;

                //NOT BEEN USED NOW
                // if (!self.trip.lodgings.length) {
                //     self.trip.lodgings = [];
                //     $(self.lodge_cities).each(function(key, val) {
                //         self.trip.lodgings.push({
                //             id: '',
                //             city_id: val.city_id,
                //             city: {
                //                 name: val.city,
                //             },
                //             date_range_list: [],
                //             lodge_name: '',
                //             stay_type_id: '',
                //             eligible_amount: val.loadge_eligible_amount,
                //             amount: '',
                //             tax: '',
                //             remarks: '',
                //         });
                //     });
                // }
                // $(self.travelled_cities_with_dates).each(function(key, val) {
                //     $(val).each(function(k, v) {
                //         self.trip.boardings.push({
                //             id: '',
                //             city_id: v.city_id,
                //             city: {
                //                 name: v.city,
                //             },
                //             expense_name: '',
                //             date: v.date,
                //             amount: '',
                //             remarks: '',
                //             eligible_amount: v.board_eligible_amount,
                //         });
                //         self.trip.local_travels.push({
                //             id: '',
                //             city_id: v.city_id,
                //             city: {
                //                 name: v.city,
                //             },
                //             mode_id: '',
                //             date: v.date,
                //             from: '',
                //             to: '',
                //             amount: '',
                //             tax: '',
                //             description: '',
                //             eligible_amount: v.local_travel_eligible_amount,
                //         });
                //     });
                // });
            } else {
                $timeout(function() {
                    // $scope.stayDaysEach();
                    // $scope.boardDaysEach();
                }, 1000);
                self.is_deviation = self.trip.employee.trip_employee_claim.is_deviation;
            }
            self.lodgings_removal_id = [];
            self.lodgings_attachment_removal_ids = [];
            self.boardings_removal_id = [];
            self.boardings_attachment_removal_ids = [];
            self.local_travels_removal_id = [];

            if (self.trip.lodgings.length == 0) {
                self.addNewLodgings();
            }
            if (!self.trip.boardings.length) {
                self.addNewBoardings();
            }
            if (self.trip.local_travels.length == 0) {
                self.addNewLocalTralvels();
            }
            setTimeout(function() {
                self.travelCal();
                self.lodgingCal();
                self.boardingCal();
                self.localTravelCal();
            }, 500);

            $rootScope.loading = false;

        });



        //TOOLTIP MOUSEOVER
        $(document).on('mouseover', ".attachment_tooltip", function() {
            var $this = $(this);
            $this.tooltip({
                title: $this.attr('data-title'),
                placement: "top"
            });
            $this.tooltip('show');
        });

        $scope.searchBookedBy;
        $scope.clearSearchBookedBy = function() {
            $scope.searchBookedBy = '';
        };

        $scope.searchLodgingCity;
        $scope.clearSearchLodgingCity = function() {
            $scope.searchLodgingCity = '';
        };

        $scope.searchStayType;
        $scope.clearSearchStayType = function() {
            $scope.searchStayType = '';
        };

        $scope.searchBoardingCity;
        $scope.clearSearchBoardingCity = function() {
            $scope.searchBoardingCity = '';
        };

        $scope.searchLocalTravelMode;
        $scope.clearSearchLocalTravelMode = function() {
            $scope.searchLocalTravelMode = '';
        };

        $scope.searchLocalTravelFrom;
        $scope.clearSearchLocalTravelFrom = function() {
            $scope.searchLocalTravelFrom = '';
        };

        $scope.searchLocalTravelTo;
        $scope.clearSearchLocalTravelTo = function() {
            $scope.searchLocalTravelTo = '';
        };


        //TIME PICKER

        // $('#timepicker1').timepicker({
        //     showInputs: false,
        // });

        // $(".form_time").datetimepicker({
        //     pickDate: false,
        //     formatViewType: 'time',
        //     format: 'HH:ii p',
        //     autoclose: true,
        //     showMeridian: true,
        //     startView: 1,
        //     maxView: 1
        // }).on("show", function() {
        //     $(".table-condensed .prev").css('visibility', 'hidden');
        //     $(".table-condensed .switch").text("Pick Time");
        //     $(".table-condensed .next").css('visibility', 'hidden');
        // });


        // DEPATURE AND ARRIVAL DATE VALIDATION
        $scope.dateArrivalDepatureValidation = function(index, type, value) {
            //alert();
            arrival_date_error_flag = 0;
            $('.visit_arrival_date').each(function(i) {
                //alert(i);
                if (self.trip.visits[i].departure_time) {
                    var index = i;
                    var first_dep_time = convert_to_24h(self.trip.visits[index].departure_time);
                    var first_arrival_time = convert_to_24h(self.trip.visits[index].arrival_time);
                    var first_dep_date_time = self.trip.visits[index].departure_date + ' ' + first_dep_time;
                    var first_arrival_date_time = self.trip.visits[index].arrival_date + ' ' + first_arrival_time;
                    var next_index = index;
                    next_index++;
                    if (self.trip.visits[next_index]) {
                        var sec_dept_time = convert_to_24h(self.trip.visits[next_index].departure_time);
                        console.log('next index' + sec_dept_time);
                        var sec_dep_date = self.trip.visits[next_index].departure_date + ' ' + sec_dept_time;
                        var d1 = first_dep_date_time.split(/[- :/*]+/);
                        var a1 = first_arrival_date_time.split(/[- :/*]+/);
                        var d2 = sec_dep_date.split(/[- :/*]+/);
                        var c = value.split(/[- :/*]+/);

                        var from = new Date(d1[2], parseInt(d1[1]) - 1, d1[0], parseInt(d1[3]), parseInt(d1[4]), d1[5]);
                        var arrival_date = new Date(a1[2], parseInt(a1[1]) - 1, a1[0], parseInt(a1[3]), parseInt(a1[4]), a1[5]);
                        var to = new Date(d2[2], parseInt(d2[1]) - 1, d2[0], parseInt(d2[3]), parseInt(d2[4]), d2[5]);

                        if (arrival_date <= from) {
                            arrival_date_error_flag++;
                            $('.arrival_date_validation_' + index).text('Please enter value greater than depature');
                        } else if (arrival_date >= to) {
                            arrival_date_error_flag++;
                            $('.arrival_date_validation_' + index).text('Please enter value lesser than next depature');
                        } else {
                            //arrival_date_error_flag = 0;
                            $('.arrival_date_validation_' + index).text('');
                        }

                    } else {
                        console.log('no index');
                        //var sec_dep_date = self.trip.end_date + ' ' + '00:00:00';
                        //console.log('sec_dep_date' );
                        var d1 = first_dep_date_time.split(/[- :/*]+/);
                        var a1 = first_arrival_date_time.split(/[- :/*]+/);
                        //var d2 = sec_dep_date.split(/[- :/*]+/);
                        var c = value.split(/[- :/*]+/);

                        var from = new Date(d1[2], parseInt(d1[1]) - 1, d1[0], parseInt(d1[3]), parseInt(d1[4]), d1[5]);
                        var arrival_date = new Date(a1[2], parseInt(a1[1]) - 1, a1[0], parseInt(a1[3]), parseInt(a1[4]), a1[5]);
                        // var to = new Date(d2[2], parseInt(d2[1]) - 1, d2[0], parseInt(d2[3]), parseInt(d2[4]), d2[5]);


                        if (arrival_date <= from) {
                            arrival_date_error_flag++;
                            $('.arrival_date_validation_' + index).text('Please enter value greater than depature');
                        } else {
                            //arrival_date_error_flag = 0;
                            $('.arrival_date_validation_' + index).text('');
                        }
                    }

                }
                console.log(' arrival_date_error_flag =' + arrival_date_error_flag);
            });
        }

        //     console.log(index, type, value)
        //     if (type && value) {
        //         var first_dep_time = convert_to_24h(self.trip.visits[index].departure_time);
        //         var first_arrival_time = convert_to_24h(self.trip.visits[index].arrival_time);
        //         var first_dep_date_time = self.trip.visits[index].departure_date + ' ' + first_dep_time;
        //         var first_arrival_date_time = self.trip.visits[index].arrival_date + ' ' + first_arrival_time;
        //         var next_index = index;
        //         next_index++;
        //         if (self.trip.visits[next_index]) {
        //             var sec_dept_time = convert_to_24h(self.trip.visits[next_index].departure_time);
        //             console.log('next index' + sec_dept_time);
        //             var sec_dep_date = self.trip.visits[next_index].departure_date + ' ' + sec_dept_time;
        //             var d1 = first_dep_date_time.split(/[- :/*]+/);
        //             var a1 = first_arrival_date_time.split(/[- :/*]+/);
        //             var d2 = sec_dep_date.split(/[- :/*]+/);
        //             var c = value.split(/[- :/*]+/);

        //             var from = new Date(d1[2], parseInt(d1[1]) - 1, d1[0], parseInt(d1[3]), parseInt(d1[4]), d1[5]);
        //             var arrival_date = new Date(a1[2], parseInt(a1[1]) - 1, a1[0], parseInt(a1[3]), parseInt(a1[4]), a1[5]);
        //             var to = new Date(d2[2], parseInt(d2[1]) - 1, d2[0], parseInt(d2[3]), parseInt(d2[4]), d2[5]);

        //             if (arrival_date <= from) {
        //                 arrival_date_error_flag = 1;
        //                 $('.arrival_date_validation_' + index).text('Please enter value greater than depature');
        //             } else if (arrival_date >= to) {
        //                 $('.arrival_date_validation_' + index).text('Please enter value lesser than next depature');
        //             } else {
        //                 arrival_date_error_flag = 0;
        //                 $('.arrival_date_validation_' + index).text('');
        //             }

        //         } else {
        //             console.log('no index');
        //             //var sec_dep_date = self.trip.end_date + ' ' + '00:00:00';
        //             //console.log('sec_dep_date' );
        //             var d1 = first_dep_date_time.split(/[- :/*]+/);
        //             var a1 = first_arrival_date_time.split(/[- :/*]+/);
        //             //var d2 = sec_dep_date.split(/[- :/*]+/);
        //             var c = value.split(/[- :/*]+/);

        //             var from = new Date(d1[2], parseInt(d1[1]) - 1, d1[0], parseInt(d1[3]), parseInt(d1[4]), d1[5]);
        //             var arrival_date = new Date(a1[2], parseInt(a1[1]) - 1, a1[0], parseInt(a1[3]), parseInt(a1[4]), a1[5]);
        //             // var to = new Date(d2[2], parseInt(d2[1]) - 1, d2[0], parseInt(d2[3]), parseInt(d2[4]), d2[5]);


        //             if (arrival_date <= from) {
        //                 arrival_date_error_flag = 1;
        //                 $('.arrival_date_validation_' + index).text('Please enter value greater than depature');
        //             } else {
        //                 arrival_date_error_flag = 0;
        //                 $('.arrival_date_validation_' + index).text('');
        //             }
        //         }
        //     }
        //     $rootScope.loading = false;

        // }

        function convert_to_24h(time_str) {
            // Convert a string like 10:05:23 PM to 24h format, returns like [22,5,23]
            var time = time_str.match(/(\d+):(\d+) (\w)/);
            var hours = Number(time[1]);
            var minutes = Number(time[2]);
            //alert(minutes);
            //var seconds = Number(time[3]);
            var meridian = time[3].toLowerCase();

            if (meridian == 'p' && hours < 12) {
                hours += 12;
            } else if (meridian == 'a' && hours == 12) {
                hours -= 12;
            }
            //alert(minutes.count());
            if (hours < 10) {
                hours = '0' + hours;
            }
            if (minutes < 10) {
                minutes = minutes + '0';
            }
            //alert(hours, minutes);
            return hours + ':' + minutes + ':00';
        };

        //ENABLE DISABLE DATE 
        $scope.dateEnableDisable = function(index, type, value) {
            if (type && value) {
                var first_dep_date = self.trip.visits[index].departure_date;
                var next_index = index;
                next_index++;
                if (self.trip.visits[next_index]) {
                    var sec_dep_date = self.trip.visits[next_index].departure_date;
                } else {
                    var sec_dep_date = self.trip.end_date;
                }

                //CHECK DATE ARE BETWEEN VALIDATED DATE
                var d1 = first_dep_date.split("-");
                var d2 = sec_dep_date.split("-");
                var c = value.split("-");

                var from = new Date(d1[2], parseInt(d1[1]) - 1, d1[0]);
                var to = new Date(d2[2], parseInt(d2[1]) - 1, d2[0]);
                var check = new Date(c[2], parseInt(c[1]) - 1, c[0]);

                if (check >= from && check <= to) {
                    return false;
                } else {
                    return true;
                }
            }

        }

        //TOOLTIP MOUSEOVER
        $(document).on('mouseover', ".separate-btn-default", function() {
            var $this = $(this);
            $this.tooltip({
                title: $this.attr('data-text'),
                placement: "top"
            });
            $this.tooltip('show');
        });

        //CLEAR REMARK
        $(document).on('click', ".clear_remarks", function() {
            var type = $(this).attr('data-type');
            $(this).closest('.separate-page-divider-wrap').find('#' + type + '_remarks').val('');
        });

        //TAB PREVIOUS BTN 
        $(document).on('click', ".expense_previous_tab", function() {
            var expensetype = $(this).attr('data-expensetype');
            // console.log(' == expensetype ==' + expensetype);
            $('.tab_li').removeClass('active');
            $('.tab_' + expensetype).addClass('active');
            $('.tab-pane').removeClass('in active');
            $('#' + expensetype + '-expenses').addClass('in active');
        });

        // $(document).on('input', ".tooltip_remarks", function() {
        //     var value = $(this).val();
        //     // console.log(' == value ==' + value);
        //     $(this).closest('.separate-btn-default').data('text', value);
        // });

        // REMARK BTN CLICK
        $(document).on('click', '.remark_btn', function() {
            var index = $(this).attr('data-index');
            var expense_type = $(this).attr('data-exptype');
            var remarks = $(this).attr('data-text');
            $('#' + expense_type + '_remarks_index').val(index);
            if (!remarks) {
                $('#' + expense_type + '_remarks').val('');
            } else {
                $('#' + expense_type + '_remarks').val(remarks);
            }

            /* $(".separate-page-divider-wrap").toggle(); */
            if ($('.separate-page-divider-wrap').hasClass('in')) {
                $(".separate-page-divider-wrap").removeClass("in");
            } else {
                $(".separate-page-divider-wrap").addClass("in");
            }
        });

        //REMARK BTN SUBMIT
        $(document).on('click', '.remark_btn_submit', function() {
            var expense_type = $(this).attr('data-exptype');
            var remarks_index = $('#' + expense_type + '_remarks_index').val();
            var remarks_text = $('#' + expense_type + '_remarks').val();

            if (expense_type == 'visit') {
                if (!self.trip.visits[remarks_index].self_booking) {
                    self.trip.visits[remarks_index].self_booking = {
                        remarks: remarks_text
                    };
                } else {
                    self.trip.visits[remarks_index].self_booking.remarks = remarks_text;
                }
            } else if (expense_type == 'lodge') {
                self.trip.lodgings[remarks_index].remarks = remarks_text;
            } else if (expense_type == 'board') {
                self.trip.boardings[remarks_index].remarks = remarks_text;
            } else if (expense_type == 'local_travel') {
                self.trip.local_travels[remarks_index].description = remarks_text;
            }
            $scope.$apply()

            if ($('.separate-page-divider-wrap').hasClass('in')) {
                $(".separate-page-divider-wrap").removeClass("in");
            } else {
                $(".separate-page-divider-wrap").addClass("in");
            }
        });


        $(document).on('click', ".separate-page-divider-btn-close", function() {
            $(".separate-page-divider-wrap").removeClass("in");
        })

        //ASSIGN ELIGIBLE AMOUNT BASED ON CITY & EXPENSE & GRADE
        $scope.assignEligibleAmount = function(city_id, type_id, index, stay_type_id) {
            if (city_id) {
                if (type_id == 3000) { //TRANSPORT EXPENSES
                    self.trip.visits[index].eligible_amount = self.cities_with_expenses[city_id].transport.eligible_amount;
                } else if (type_id == 3001) { // LODGING EXPENSE
                    if (stay_type_id == 3341) {
                        self.trip.lodgings[index].eligible_amount = self.cities_with_expenses[city_id].lodge.home.eligible_amount;
                    } else if (stay_type_id == 3340) {
                        self.trip.lodgings[index].eligible_amount = self.cities_with_expenses[city_id].lodge.normal.eligible_amount;
                    } else {
                        self.trip.lodgings[index].eligible_amount = '0.00';
                    }
                    $timeout(function() {
                        $scope.stayDaysEach();
                    }, 100);
                } else if (type_id == 3002) { // BOARDING EXPENSE
                    self.trip.boardings[index].eligible_amount = self.cities_with_expenses[city_id].board.eligible_amount;
                    $timeout(function() {
                        $scope.boardDaysEach();
                    }, 100);
                } else if (type_id == 3003) { // LOCAL TRAVEL EXPENSE
                }
            } else {
                if (type_id == 3000) { //TRANSPORT EXPENSES
                    self.trip.visits[index].eligible_amount = '0.00';
                } else if (type_id == 3001) { // LODGING EXPENSE
                    self.trip.lodgings[index].eligible_amount = '0.00';
                    $timeout(function() {
                        $scope.stayDaysEach();
                    }, 100);
                } else if (type_id == 3002) { // BOARDING EXPENSE
                    self.trip.boardings[index].eligible_amount = '0.00';
                    $timeout(function() {
                        $scope.boardDaysEach();
                    }, 100);
                } else if (type_id == 3003) { // LOCAL TRAVEL EXPENSE
                }
            }
        }


        $scope.getEligibleAmtBasedonCitycategoryGrade = function(grade_id, city_id, expense_type_id, key) {
            if (city_id && grade_id && expense_type_id) {
                // console.log(grade_id, city_id, expense_type_id, key);
                $.ajax({
                        url: get_eligible_amount_by_city_category_grade,
                        method: "GET",
                        data: { city_id: city_id, grade_id: grade_id, expense_type_id: expense_type_id },
                    })
                    .done(function(res) {
                        // console.log(res.grade_expense_type);
                        var eligible_amount = res.grade_expense_type ? res.grade_expense_type.eligible_amount : '0.00';
                        // console.log(' == eligible_amount ==' + eligible_amount);
                        if (expense_type_id == 3000) { //TRANSPORT EXPENSES
                            self.trip.visits[key].eligible_amount = eligible_amount;
                        } else if (expense_type_id == 3001) { // LODGING EXPENSE
                            self.trip.lodgings[key].eligible_amount = eligible_amount;
                        } else if (expense_type_id == 3002) { // BOARDING EXPENSE
                            self.trip.boardings[key].eligible_amount = eligible_amount;
                            $scope.boardDaysEach();
                        } else if (expense_type_id == 3003) { // LOCAL TRAVEL EXPENSE
                            self.trip.local_travels[key].eligible_amount = eligible_amount;
                        }
                        $scope.$apply()
                    })
                    .fail(function(xhr) {
                        console.log(xhr);
                    });
            }
        }

        $scope.getEligibleAmtBasedonCitycategoryGradeStayType = function(grade_id, city_id, expense_type_id, key, stay_type_id) {
            if (city_id && grade_id && expense_type_id && stay_type_id) {
                $.ajax({
                        url: get_eligible_amount_by_city_category_grade_staytype,
                        method: "GET",
                        data: { city_id: city_id, grade_id: grade_id, expense_type_id: expense_type_id, stay_type_id: stay_type_id },
                    })
                    .done(function(res) {
                        var eligible_amount_after_per = res.eligible_amount;
                        self.trip.lodgings[key].eligible_amount = eligible_amount_after_per;
                        $scope.$apply()
                        $scope.stayDaysEach();
                    })
                    .fail(function(xhr) {
                        console.log(xhr);
                    });
            }
        }

        //GET CLAIM STATUS BY TRNASPORT MODE IN TRANSPORT EXPENSES
        $scope.getVisitTrnasportModeClaimStatus = function(travel_mode_id, key) {
            if (travel_mode_id) {
                $.ajax({
                        url: get_claim_status_by_travel_mode_id,
                        method: "GET",
                        data: { travel_mode_id: travel_mode_id },
                    })
                    .done(function(res) {
                        //console.log(res.is_no_vehicl_claim);
                        var is_no_vehicl_claim = res.is_no_vehicl_claim;
                        //IF TRANSPORT HAS NO VEHICLE CLAIM
                        if (is_no_vehicl_claim) {
                            if (!self.trip.visits[key].self_booking) {
                                self.trip.visits[key].self_booking = {
                                    amount: '0.00',
                                    tax: '0.00',
                                    reference_number: '--',
                                    readonly: true
                                };
                            } else {
                                self.trip.visits[key].self_booking.readonly = true;
                                self.trip.visits[key].self_booking.amount = '0.00';
                                self.trip.visits[key].self_booking.tax = '0.00';
                                self.trip.visits[key].self_booking.reference_number = '--';
                            }

                        } else {
                            if (!self.trip.visits[key].self_booking) {
                                self.trip.visits[key].self_booking = {
                                    amount: '',
                                    tax: '',
                                    reference_number: '',
                                    readonly: false
                                };
                            } else {
                                self.trip.visits[key].self_booking.readonly = false;
                                self.trip.visits[key].self_booking.amount = '';
                                self.trip.visits[key].self_booking.tax = '';
                                self.trip.visits[key].self_booking.reference_number = '';
                            }

                        }

                        $scope.$apply();
                        self.travelCal();
                    })
                    .fail(function(xhr) {
                        console.log(xhr);
                    });
            }
        }

        $scope.isDeviation = function() {
            var is_deviation = false;
            $('.is_deviation_amount').each(function() {
                var amount_entered = $(this).val();
                var default_eligible_amount = $(this).closest('.is_deviation_amount_row').find('.eligible_amount').val();
                if (!$.isNumeric(amount_entered)) {
                    amount_entered = 0;
                } else {
                    amount_entered = parseInt(amount_entered);
                }
                if (!$.isNumeric(default_eligible_amount)) {
                    default_eligible_amount = 0;
                } else {
                    default_eligible_amount = parseInt(default_eligible_amount);
                }
                if (amount_entered > default_eligible_amount) {
                    is_deviation = true;
                }
            });
            if (is_deviation) {
                $('#is_deviation').val(1);
            } else {
                $('#is_deviation').val(0);
            }
        }

        //LODGE STAY DAYS CALC
        $scope.stayDaysEach = function() {
            $('.stayed_days').each(function() {
                var stayed_days = $(this).val();
                var stayed_base_eligible_amount = $(this).closest('tr').find('.base_eligible_amount').val();
                var stayed_eligible_amount_with_days = 0;
                // console.log(' == stayed_base_eligible_amount ==' + stayed_base_eligible_amount);

                if (!$.isNumeric(stayed_days)) {
                    stayed_eligible_amount_with_days = stayed_base_eligible_amount;
                } else {
                    stayed_eligible_amount_with_days = stayed_days * stayed_base_eligible_amount;
                }
                stayed_eligible_amount_with_days = parseFloat(Math.round(stayed_eligible_amount_with_days * 100) / 100).toFixed(2);
                // console.log(' == stayed_eligible_amount_with_days ==' + stayed_eligible_amount_with_days);

                $(this).closest('tr').find('.eligible_amount').val(stayed_eligible_amount_with_days);
                $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + stayed_eligible_amount_with_days);
                $scope.isDeviation();
            });
        }

        $(document).on('input', '.stayed_days', function() {
            var stayed_days = $(this).val();
            var stayed_base_eligible_amount = $(this).closest('tr').find('.base_eligible_amount').val();
            var stayed_eligible_amount_with_days = 0;

            if (!$.isNumeric(stayed_days)) {
                stayed_eligible_amount_with_days = stayed_base_eligible_amount;
            } else {
                stayed_eligible_amount_with_days = stayed_days * stayed_base_eligible_amount;
            }
            stayed_eligible_amount_with_days = parseFloat(Math.round(stayed_eligible_amount_with_days * 100) / 100).toFixed(2);

            $(this).closest('tr').find('.eligible_amount').val(stayed_eligible_amount_with_days);
            $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + stayed_eligible_amount_with_days);
            $scope.isDeviation();
        });


        //BOARDING DAYS CALC
        $scope.boardDaysEach = function() {
            $('.boarding_days').each(function() {
                var boarding_days = $(this).val();
                var boarding_days_base_eligible_amount = $(this).closest('tr').find('.boarding_base_eligible_amount').val();
                var boarding_eligible_amount_with_days = 0;
                // console.log(' == boarding_days_base_eligible_amount eachhh ==' + boarding_days_base_eligible_amount);
                if (!$.isNumeric(boarding_days)) {
                    boarding_eligible_amount_with_days = boarding_days_base_eligible_amount;
                } else {
                    boarding_eligible_amount_with_days = boarding_days * boarding_days_base_eligible_amount;
                }
                boarding_eligible_amount_with_days = parseFloat(Math.round(boarding_eligible_amount_with_days * 100) / 100).toFixed(2);
                // console.log(' == boarding_eligible_amount_with_days eachhhh ==' + boarding_eligible_amount_with_days);
                $(this).closest('tr').find('.eligible_amount').val(boarding_eligible_amount_with_days);
                $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + boarding_eligible_amount_with_days);
                $scope.isDeviation();
            });
        }

        $(document).on('input', '.boarding_days', function() {
            var boarding_days = $(this).val();
            var boarding_days_base_eligible_amount = $(this).closest('tr').find('.boarding_base_eligible_amount').val();
            var boarding_eligible_amount_with_days = 0;
            // console.log(' == boarding_days_base_eligible_amount inputttt ==' + boarding_days_base_eligible_amount);

            if (!$.isNumeric(boarding_days)) {
                boarding_eligible_amount_with_days = boarding_days_base_eligible_amount;
            } else {
                boarding_eligible_amount_with_days = boarding_days * boarding_days_base_eligible_amount;
            }
            boarding_eligible_amount_with_days = parseFloat(Math.round(boarding_eligible_amount_with_days * 100) / 100).toFixed(2);
            // console.log(' == boarding_eligible_amount_with_days inputttt  ==' + boarding_eligible_amount_with_days);

            $(this).closest('tr').find('.eligible_amount').val(boarding_eligible_amount_with_days);
            $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + boarding_eligible_amount_with_days);
            $scope.isDeviation();
        });

        /*self.addLodgingExpenses = function() {
            self.lodging.push({
                visit_date: '',
                booking_method: 'Self',
                preferred_travel_modes: '',
            });
        }*/
        // $(function() {
        //     $('.form_datetime').datetimepicker();
        // });

        // $(".form_datetime").datetimepicker({
        //     format: "yyyy-m-dd hh:ii",
        //     autoclose: true,
        //     todayBtn: true,
        //     pickerPosition: "bottom-left"
        // });

        /* Pane Next Button */
        $('.btn-nxt').on("click", function() {
            $('.editDetails-tabs li.active').next().children('a').trigger("click");
        });
        $('.btn-prev').on("click", function() {
            $('.editDetails-tabs li.active').prev().children('a').trigger("click");
        });

        //GET VISIT DEPARTURE DATE TO FIND DAYS CALC
        $scope.visitDepartureArrivalDate = function() {
            // console.log(' == visitDepartureArrivalDate ==');
            $timeout(function() {
                var last_visit_index = self.trip.visits.length - 1;
                var days_aft_calc = 0;
                // console.log(self.trip.visits[0].departure_date, self.trip.visits[0].departure_time, self.trip.visits[last_visit_index].arrival_date, self.trip.visits[last_visit_index].arrival_time);
                // console.log(' == cond ==');
                if (self.trip.visits[0].departure_time != '' && typeof self.trip.visits[0].departure_time !== "undefined") {
                    if (self.trip.visits[last_visit_index].arrival_date != '') {
                        if (self.trip.visits[last_visit_index].arrival_time != '' && typeof self.trip.visits[last_visit_index].arrival_time !== "undefined") {
                            var date_1 = self.trip.visits[0].departure_date.split("-");
                            var date_2 = self.trip.visits[last_visit_index].arrival_date.split("-");
                            var visit_departure_date_format = date_1[1] + '/' + date_1[0] + '/' + date_1[2];
                            var visit_arrival_date_format = date_2[1] + '/' + date_2[0] + '/' + date_2[2];

                            // console.log(self.trip.visits[0].departure_date, self.trip.visits[0].departure_time, self.trip.visits[last_visit_index].arrival_date, self.trip.visits[last_visit_index].arrival_time);

                            var visit_departure_time = self.trip.visits[0].departure_time;
                            var visit_arrival_time = self.trip.visits[last_visit_index].arrival_time;

                            var timeDiff = (new Date(visit_arrival_date_format + ' ' + visit_arrival_time)) - (new Date(visit_departure_date_format + ' ' + visit_departure_time));
                            // var days = (timeDiff / (1000 * 60 * 60 * 24)) + 1;
                            var hours = Math.abs(timeDiff / 3600000);
                            // console.log(' == hours ==' + hours);

                            if (hours > 24) {
                                var days = Math.round(hours / 24);
                                days_aft_calc = days;
                            } else {
                                days_aft_calc = 1;
                            }
                            // console.log(' == days ==' + days_aft_calc);
                            $('.trip_no_of_days').html(days_aft_calc);
                            $('.trip_total_days').val(days_aft_calc);
                        } else {
                            $('.trip_no_of_days').html('--');
                            $('.trip_total_days').val(0);
                        }
                    } else {
                        $('.trip_no_of_days').html('--');
                        $('.trip_total_days').val(0);
                    }
                } else {
                    $('.trip_no_of_days').html('--');
                    $('.trip_total_days').val(0);
                }

            }, 100);
        }


        //GET LODGE CHECKIN AND CHECKOUT DATE TO FIND STAY DAYS AND AMOUNT CALC
        $scope.lodgecheckOutInDate = function() {
            // console.log(' == lodgecheckOutInDate ==');
            $timeout(function() {
                $('.lodging_checkin_out_date').each(function() {
                    var checkin_date = $(this).closest('tr').find('.lodging_checkin_date').val();
                    var checkout_date = $(this).closest('tr').find('.lodging_check_out_date').val();
                    var checkin_time = $(this).closest('tr').find('.lodging_checkin_time').val();
                    var checkout_time = $(this).closest('tr').find('.lodging_check_out_time').val();
                    var base_eligible_amount = $(this).closest('tr').find('.base_eligible_amount').val();
                    var date_1 = checkin_date.split("-");
                    var date_2 = checkout_date.split("-");
                    var checkin_date_format = date_1[1] + '/' + date_1[0] + '/' + date_1[2];
                    var checkout_date_format = date_2[1] + '/' + date_2[0] + '/' + date_2[2];
                    // console.log(checkin_date_format, checkin_time, checkout_date_format, checkout_time);

                    if (checkin_date_format && checkout_date_format && checkin_time && checkout_time) {
                        var timeDiff = (new Date(checkout_date_format + ' ' + checkout_time)) - (new Date(checkin_date_format + ' ' + checkin_time));
                        // var days = (timeDiff / (1000 * 60 * 60 * 24)) + 1;
                        var hours = Math.abs(timeDiff / 3600000);
                        // console.log(' == hours ==' + hours);
                        if (hours > 24) {
                            var days = Math.round(hours / 24);
                            // var days_reminder = Math.round(hours % 24);
                            // var days_aft_calc;
                            // if (days_reminder > 2) {
                            //     days_aft_calc = days + 1;
                            // } else {
                            days_aft_calc = days;
                            // }
                        } else {
                            days_aft_calc = 1;
                        }

                        var eligible_amount_with_days = 0;
                        var days_c = '';
                        if (!$.isNumeric(days_aft_calc)) {
                            days_c = '';
                            eligible_amount_with_days = base_eligible_amount;
                        } else {
                            days_c = days_aft_calc;
                            eligible_amount_with_days = days_aft_calc * base_eligible_amount;
                        }
                        eligible_amount_with_days = parseFloat(Math.round(eligible_amount_with_days * 100) / 100).toFixed(2);
                        $(this).closest('tr').find('.stayed_days').val(days_c);
                        $(this).closest('tr').find('.eligible_amount').val(eligible_amount_with_days);
                        $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + eligible_amount_with_days);
                        $scope.isDeviation();
                    }
                });
            }, 100);
        }

        //GET BOARDING FROM & TO DATE TO FINDDAYS AND AMOUNT CALC
        $scope.boardingFromToDate = function() {
            $timeout(function() {
                $('.boarding_from_to_date').each(function() {
                    var checkin_date = $(this).closest('tr').find('.boarding_from_date').val();
                    var checkout_date = $(this).closest('tr').find('.boarding_to_date').val();
                    var base_eligible_amount = $(this).closest('tr').find('.boarding_base_eligible_amount').val();

                    var date_1 = checkin_date.split("-");
                    var date_2 = checkout_date.split("-");
                    var checkin_date_format = date_1[1] + '/' + date_1[0] + '/' + date_1[2];
                    var checkout_date_format = date_2[1] + '/' + date_2[0] + '/' + date_2[2];
                    if (checkin_date_format && checkout_date_format) {
                        var timeDiff = (new Date(checkout_date_format)) - (new Date(checkin_date_format));
                        var days = (timeDiff / (1000 * 60 * 60 * 24)) + 1;
                        var eligible_amount_with_days = 0;
                        var days_c = '';
                        if (!$.isNumeric(days)) {
                            days_c = '';
                            eligible_amount_with_days = base_eligible_amount;
                        } else {
                            days_c = days;
                            eligible_amount_with_days = days * base_eligible_amount;
                        }
                        eligible_amount_with_days = parseFloat(Math.round(eligible_amount_with_days * 100) / 100).toFixed(2);
                        $(this).closest('tr').find('.boarding_days').val(days_c);
                        $(this).closest('tr').find('.eligible_amount').val(eligible_amount_with_days);
                        $(this).closest('tr').find('.eligible_amount_label').html('Eligible - ₹ ' + eligible_amount_with_days);
                        $scope.isDeviation();
                    }
                });
            }, 100);
        }

        //Check Departure Date & Arrival Date Value Exist 
        //NOTE: IT'S NOT BEEN USED NOW
        $scope.CheckDateValExist = function() {
            var form_id = '#claim_form';
            var transport_expense_date_count = $('.transport_expense_date').length;
            var transport_expense_date_value_exist_count = 0;
            $('.transport_expense_date').each(function() {
                var date_exist = $(this).val();
                if (date_exist) {
                    transport_expense_date_value_exist_count++;
                }
            });
            if (transport_expense_date_count == transport_expense_date_value_exist_count) {
                let formData = new FormData($(form_id)[0]);
                $.ajax({
                        url: eyatra_trip_get_expense_data_url,
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                    })
                    .done(function(res) {
                        console.log(res);
                        self.trip.boardings = [];
                        self.trip.lodgings = [];
                        self.trip.local_travels = [];

                        $(res['lodge_cities']).each(function(key, val) {
                            self.trip.lodgings.push({
                                id: '',
                                city_id: val.city_id,
                                city: {
                                    name: val.city,
                                },
                                lodge_name: '',
                                stay_type_id: '',
                                eligible_amount: val.loadge_eligible_amount,
                                amount: '',
                                tax: '',
                                remarks: '',
                            });
                        });

                        $(res['travelled_cities_with_dates']).each(function(key, val) {
                            $(val).each(function(k, v) {
                                self.trip.boardings.push({
                                    id: '',
                                    city_id: v.city_id,
                                    city: {
                                        name: v.city,
                                    },
                                    expense_name: '',
                                    date: v.date,
                                    amount: '',
                                    remarks: '',
                                    eligible_amount: v.board_eligible_amount,
                                });
                                self.trip.local_travels.push({
                                    id: '',
                                    city_id: v.city_id,
                                    city: {
                                        name: v.city,
                                    },
                                    mode_id: '',
                                    date: v.date,
                                    from: '',
                                    to: '',
                                    amount: '',
                                    tax: '',
                                    description: '',
                                    eligible_amount: v.local_travel_eligible_amount,
                                });
                            });
                        });
                        $scope.$apply()
                        // console.log(self.trip.boardings);
                    })
                    .fail(function(xhr) {
                        custom_noty('error', 'Something went wrong at server');
                    });
            }
        }

        // Lodgings
        self.addNewLodgings = function() {
            self.trip.lodgings.push({
                id: '',
                city_id: '',
                lodge_name: '',
                stay_type_id: '',
                eligible_amount: '0.00',
                check_in_date: '',
                checkout_date: '',
                stayed_days: '',
                amount: '',
                tax: '',
                gstin: '',
                reference_number: '',
                lodge_name: '',
                remarks: '',
            });
        }
        //REMOVE LODGING
        self.removeLodging = function(index, lodging_id) {
            if (lodging_id) {
                self.lodgings_removal_id.push(lodging_id);
                $('#lodgings_removal_id').val(JSON.stringify(self.lodgings_removal_id));
            }
            self.trip.lodgings.splice(index, 1);
            setTimeout(function() {
                self.lodgingCal();
            }, 500);
        }

        //REMOVE LODGING ATTACHMENT
        self.removeLodgingAttachment = function(lodge_attachment_index, lodge_attachment_id) {
            console.log(lodge_attachment_id, lodge_attachment_index);
            if (lodge_attachment_id) {
                self.lodgings_attachment_removal_ids.push(lodge_attachment_id);
                $('#lodgings_attach_removal_ids').val(JSON.stringify(self.lodgings_attachment_removal_ids));
            }
            self.trip.lodging_attachments.splice(lodge_attachment_index, 1);
        }

        // Boardings
        self.addNewBoardings = function() {
            self.trip.boardings.push({
                id: '',
                city_id: '',
                // date_range_list: self.boarding_dates_list,
                from_date: '',
                to_date: '',
                days: '',
                expense_name: '',
                amount: '',
                tax: '',
                remarks: '',
                eligible_amount: '0.00',
                attachments: [],
            });
        }
        //REMOVE BOARDING
        self.removeBoarding = function(index, boarding_id) {
            if (boarding_id) {
                self.boardings_removal_id.push(boarding_id);
                $('#boardings_removal_id').val(JSON.stringify(self.boardings_removal_id));
            }
            self.trip.boardings.splice(index, 1);
            setTimeout(function() {
                self.boardingCal();
            }, 500);
        }

        //REMOVE BOARDING ATTACHMENT
        self.removeBoardingAttachment = function(board_attachment_index, board_attachment_id) {
            console.log(board_attachment_id, board_attachment_index);
            if (board_attachment_id) {
                self.boardings_attachment_removal_ids.push(board_attachment_id);
                $('#boardings_attach_removal_ids').val(JSON.stringify(self.boardings_attachment_removal_ids));
            }
            self.trip.boarding_attachments.splice(board_attachment_index, 1);
        }

        // LocalTralvels
        self.addNewLocalTralvels = function() {
            self.trip.local_travels.push({
                id: '',
                // date_range_list: self.local_travel_dates_list,
                mode_id: '',
                date: '',
                from_id: '',
                to_id: '',
                amount: '',
                description: '',
                eligible_amount: '0.00',
            });
        }
        self.removeLocalTralvel = function(index, local_travel_id) {
            if (local_travel_id) {
                self.local_travels_removal_id.push(local_travel_id);
                $('#local_travels_removal_id').val(JSON.stringify(self.local_travels_removal_id));
            }
            self.trip.local_travels.splice(index, 1);
            setTimeout(function() {
                self.localTravelCal();
            }, 500);
        }
        self.travelCal = function() {
            //alert();
            var total_travel_amount = 0;
            $('.travel_amount').each(function() {
                var travel_amount = parseInt($(this).closest('.is_deviation_amount_row').find('.travel_amount').val() || 0);
                //alert(travel_amount);
                var travel_tax = parseInt($(this).closest('.is_deviation_amount_row').find('.travel_tax').val() || 0);
                //alert(travel_tax);
                if (!$.isNumeric(travel_amount)) {
                    travel_amount = 0;
                }
                if (!$.isNumeric(travel_tax)) {
                    travel_tax = 0;
                }
                travel_current_total = travel_amount + travel_tax;
                total_travel_amount += travel_current_total;
                $(this).closest('tr').find('.visit_booking_total_amount').val(travel_current_total);
            });
            // console.log(total_travel_amount);
            $('.transport_expenses').text('₹ ' + total_travel_amount.toFixed(2));
            $('.total_travel_amount').val(total_travel_amount.toFixed(2));
            caimTotalAmount();
        }
        self.lodgingCal = function() {
            var total_lodging_amount = 0;
            $('.lodging_amount').each(function() {
                var lodging_amount = parseInt($(this).closest('.is_deviation_amount_row').find('#lodging_amount').val() || 0);
                //alert(lodging_amount);
                var lodging_tax = parseInt($(this).closest('.is_deviation_amount_row').find('#lodging_tax').val() || 0);
                if (!$.isNumeric(lodging_amount)) {
                    lodging_amount = 0;
                }
                if (!$.isNumeric(lodging_tax)) {
                    lodging_tax = 0;
                }
                current_total = lodging_amount + lodging_tax;
                total_lodging_amount += current_total;
                $(this).closest('tr').find('.lodge_total_amount').val(current_total);
            });
            // console.log(total_lodging_amount);
            $('.lodging_expenses').text('₹ ' + total_lodging_amount.toFixed(2));
            $('.total_lodging_amount').val(total_lodging_amount.toFixed(2));
            caimTotalAmount();
        }

        self.boardingCal = function() {
            //alert();
            var total_boarding_amount = 0;
            $('.boarding_amount').each(function() {
                var boarding_amount = parseFloat($(this).closest('.is_deviation_amount_row').find('#boarding_amount').val() || 0);
                var boarding_tax = parseFloat($(this).closest('.is_deviation_amount_row').find('#boarding_tax').val() || 0);
                //console.log(boarding_amount, boarding_tax);
                if (!$.isNumeric(boarding_amount)) {
                    boarding_amount = 0;
                }
                if (!$.isNumeric(boarding_tax)) {
                    boarding_tax = 0;
                }
                current_boarding_total = boarding_amount + boarding_tax;
                total_boarding_amount += current_boarding_total;
            });
            // console.log(total_boarding_amount);
            $('.boarding_expenses').text('₹ ' + total_boarding_amount.toFixed(2));
            $('.total_boarding_amount').val(total_boarding_amount.toFixed(2));
            caimTotalAmount();
        }

        self.localTravelCal = function() {
            //alert();
            var total_local_travel_amount = 0;
            $('.local_travel_amount').each(function() {
                var local_travel_amount = parseFloat($(this).closest('.is_deviation_amount_row').find('#local_travel_amount').val() || 0);
                var local_travel_tax = parseFloat($(this).closest('.is_deviation_amount_row').find('#local_travel_tax').val() || 0);
                // console.log(local_travel_amount, local_travel_tax);
                if (!$.isNumeric(local_travel_amount)) {
                    local_travel_amount = 0;
                }
                if (!$.isNumeric(local_travel_tax)) {
                    local_travel_tax = 0;
                }
                current_boarding_total = local_travel_amount + local_travel_tax;
                total_local_travel_amount += current_boarding_total;
            });
            // console.log(total_local_travel_amount);
            $('.local_expenses').text('₹ ' + total_local_travel_amount.toFixed(2));
            $('.total_local_travel_amount').val(total_local_travel_amount.toFixed(2));
            caimTotalAmount();
        }


        function caimTotalAmount() {

            var total_travel_amount = parseFloat($('.total_travel_amount').val() || 0);
            var total_lodging_amount = parseFloat($('.total_lodging_amount').val() || 0);
            var total_boarding_amount = parseFloat($('.total_boarding_amount').val() || 0);
            var total_local_travel_amount = parseFloat($('.total_local_travel_amount').val() || 0);
            var total_claim_amount = total_travel_amount + total_lodging_amount + total_boarding_amount + total_local_travel_amount;
            $('.claim_total_amount').val(total_claim_amount.toFixed(2));
            $('.claim_total_amount').text('₹ ' + total_claim_amount.toFixed(2));

            // console.log('total claim' + total_claim_amount);
        }

        //Form submit validation
        self.claimSubmit = function() {
            //alert();
            // $('#claim_form').on('submit', function(event) {
            //Add validation rule for dynamically generated name fields
            // $('.maxlength_name').each(function() {
            //     $(this).rules("add", {
            //         required: true,
            //         maxlength: 191,
            //     });
            // });
            $('.num_amount').each(function() {
                $(this).rules("add", {
                    maxlength: 12,
                    number: true,
                    // required: true,
                });
            });
            // $('.boarding_expense').each(function() {
            //     $(this).rules("add", {
            //         maxlength: 255,
            //         required: true,
            //     });
            // });
        }

        //SWITCH TAB
        $(document).on('click', '.tab_nav_expense', function() {
            self.enable_switch_tab = false;
            //GET ACTIVE FORM DATA NAV
            var active_tab_type = $('.tab_li.active .tab_nav_expense').attr('data-nav_form');
            var selected_tab_type = $(this).attr('data-nav_tab');
            if (active_tab_type) { //EXCEPT LOCAL TRAVEL NAV
                $('#claim_' + active_tab_type + '_expense_form').submit();
            } else {
                self.enable_switch_tab = true;
            }
            // console.log(' == self.enable_switch_tab ==');
            // console.log(self.enable_switch_tab);
            if (self.enable_switch_tab) {
                $('.tab_li').removeClass('active');
                $('.tab_' + selected_tab_type).addClass('active');
                $('.tab-pane').removeClass('in active');
                $('#' + selected_tab_type + '-expenses').addClass('in active');
            }

        });



        //FORM SUBMIT
        $(document).on('click', '.claim_submit_btn', function() {
            self.enable_switch_tab = false;
            //GET ACTIVE FORM 
            //alert();
            var current_form = $(this).attr('data-submit_type');
            var next_form = $(this).attr('data-next_submit_type');
            $('#claim_' + current_form + '_expense_form').submit();
            // $timeout(function() {
            // console.log(' == self.enable_switch_tab ==');
            // console.log(self.enable_switch_tab);
            if (self.enable_switch_tab) {
                $('.tab_li').removeClass('active');
                $('.tab_' + next_form).addClass('active');
                $('.tab-pane').removeClass('in active');
                $('#' + next_form + '-expenses').addClass('in active');
            }
            // }, 1000);
        });

        //TRANSPORT FORM SUBMIT
        var form_transport_id = '#claim_transport_expense_form';
        $.validator.addClassRules({
            maxlength_gstin: {
                maxlength: 15,
            }
        });
        var v = jQuery(form_transport_id).validate({
            ignore: "",
            rules: {},
            errorElement: "div", // default is 'label'
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent())
            },
            submitHandler: function(form) {
                //alert('123');
                // alert(arrival_date_error_flag);
                if (arrival_date_error_flag == 0) {

                    let formData = new FormData($(form_transport_id)[0]);
                    $('#transport_submit').html('loading');
                    $("#transport_submit").attr("disabled", true);
                    self.enable_switch_tab = false;
                    $.ajax({
                            url: eyatra_trip_claim_save_url,
                            method: "POST",
                            data: formData,
                            processData: false,
                            contentType: false,
                            async: false,
                        })
                        .done(function(res) {
                            console.log(res);
                            if (!res.success) {
                                $('#transport_submit').html('Save & Next');
                                $("#transport_submit").attr("disabled", false);
                                var errors = '';
                                for (var i in res.errors) {
                                    errors += '<li>' + res.errors[i] + '</li>';
                                }
                                custom_noty('error', errors);
                                self.enable_switch_tab = false;
                                $scope.$apply()
                            } else {
                                $noty = new Noty({
                                    type: 'success',
                                    layout: 'topRight',
                                    text: 'Transport expenses saved successfully!!',
                                    animation: {
                                        speed: 500 // unavailable - no need
                                    },
                                }).show();
                                setTimeout(function() {
                                    $noty.close();
                                }, 5000);
                                // $(res.lodge_checkin_out_date_range_list).each(function(key, val) {
                                //     self.trip.lodgings[key].date_range_list = val;
                                // });// $scope.$apply()
                                // $('.tab_li').removeClass('active');
                                // $('.tab_lodging').addClass('active');
                                // $('.tab-pane').removeClass('in active');
                                // $('#lodging-expenses').addClass('in active');
                                self.enable_switch_tab = true;
                                $scope.$apply()
                                $('#transport_submit').html('Save & Next');
                                $("#transport_submit").attr("disabled", false);
                            }
                        })
                        .fail(function(xhr) {
                            $('#transport_submit').html('Save & Next');
                            $("#transport_submit").attr("disabled", false);
                            custom_noty('error', 'Something went wrong at server');
                        });
                } else {
                    $noty = new Noty({
                        type: 'error',
                        layout: 'topRight',
                        text: 'Please correct the Depature and arrival dates',
                    }).show();
                    setTimeout(function() {
                        $noty.close();
                    }, 5000);
                }
            },
        });

        //LODGE FORM SUBMIT
        var form_lodge_id = '#claim_lodge_expense_form';
        $.validator.addClassRules({
            maxlength_gstin: {
                maxlength: 15,
            }
        });
        var v = jQuery(form_lodge_id).validate({
            ignore: "",
            errorElement: "div", // default is 'label'
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent())
            },
            rules: {},
            submitHandler: function(form) {
                //console.log(self.item);
                let formData = new FormData($(form_lodge_id)[0]);
                $('#lodge_submit').html('loading');
                $("#lodge_submit").attr("disabled", true);
                self.enable_switch_tab = false;
                $.ajax({
                        url: eyatra_trip_claim_save_url,
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                        async: false,
                    })
                    .done(function(res) {
                        if (!res.success) {
                            $('#lodge_submit').html('Save & Next');
                            $("#lodge_submit").attr("disabled", false);
                            var errors = '';
                            for (var i in res.errors) {
                                errors += '<li>' + res.errors[i] + '</li>';
                            }
                            custom_noty('error', errors);
                            self.enable_switch_tab = false;
                            $scope.$apply()
                        } else {
                            $noty = new Noty({
                                type: 'success',
                                layout: 'topRight',
                                text: 'Lodge expenses saved successfully!!',
                                animation: {
                                    speed: 500 // unavailable - no need
                                },
                            }).show();
                            setTimeout(function() {
                                $noty.close();
                            }, 5000);
                            // self.boarding_dates_list = res.boarding_dates_list;
                            // self.local_travel_dates_list = res.boarding_dates_list;
                            // if (!self.trip.boardings.length) {
                            //     self.addNewBoardings();
                            // } else {
                            //     $(self.trip.boardings).each(function(key, val) {
                            //         self.trip.boardings[key].date_range_list = self.boarding_dates_list;
                            //     });
                            // }
                            // if (!self.trip.local_travels.length) {
                            //     self.addNewLocalTralvels();
                            // } else {
                            //     $(self.trip.local_travels).each(function(key, val) {
                            //         self.trip.local_travels[key].date_range_list = self.local_travel_dates_list;
                            //     });
                            // }

                            //REFRESH LODGINGS
                            self.trip.lodgings = res.saved_lodgings.lodgings;
                            self.lodgings_removal_id = [];
                            self.lodgings_attachment_removal_ids = [];
                            $('#lodgings_attach_removal_ids').val('');
                            self.enable_switch_tab = true;
                            $scope.$apply()
                            // $('.tab_li').removeClass('active');
                            // $('.tab_boarding').addClass('active');
                            // $('.tab-pane').removeClass('in active');
                            // $('#boarding-expenses').addClass('in active');
                            $('#lodge_submit').html('Save & Next');
                            $("#lodge_submit").attr("disabled", false);
                        }
                    })
                    .fail(function(xhr) {
                        $('#lodge_submit').html('Save & Next');
                        $("#lodge_submit").attr("disabled", false);
                        custom_noty('error', 'Something went wrong at server');
                    });

            },
        });

        //BOARD FORM SUBMIT
        var form_board_id = '#claim_board_expense_form';
        var v = jQuery(form_board_id).validate({
            ignore: "",
            rules: {},
            errorElement: "div", // default is 'label'
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent())
            },
            submitHandler: function(form) {
                //console.log(self.item);
                let formData = new FormData($(form_board_id)[0]);
                $('#board_submit').html('loading');
                $("#board_submit").attr("disabled", true);
                self.enable_switch_tab = false;
                $.ajax({
                        url: eyatra_trip_claim_save_url,
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                        async: false,
                    })
                    .done(function(res) {
                        console.log(res);
                        if (!res.success) {
                            $('#board_submit').html('Save & Next');
                            $("#board_submit").attr("disabled", false);
                            var errors = '';
                            for (var i in res.errors) {
                                errors += '<li>' + res.errors[i] + '</li>';
                            }
                            custom_noty('error', errors);
                            self.enable_switch_tab = false;
                            $scope.$apply()
                        } else {
                            $noty = new Noty({
                                type: 'success',
                                layout: 'topRight',
                                text: 'Boarding expenses saved successfully!!',
                                animation: {
                                    speed: 500 // unavailable - no need
                                },
                            }).show();
                            setTimeout(function() {
                                $noty.close();
                            }, 5000);
                            // $('.tab_li').removeClass('active');
                            // $('.tab_local_travel').addClass('active');
                            // $('.tab-pane').removeClass('in active');
                            // $('#local_travel-expenses').addClass('in active');

                            //REFRESH BOARDINGS
                            self.trip.boardings = res.saved_boardings.boardings;
                            self.boardings_removal_id = [];
                            self.boardings_attachment_removal_ids = [];
                            $('#boardings_attach_removal_ids').val('');

                            self.enable_switch_tab = true;
                            $scope.$apply()
                            $('#board_submit').html('Save & Next');
                            $("#board_submit").attr("disabled", false);
                        }
                    })
                    .fail(function(xhr) {
                        $('#board_submit').html('Save & Next');
                        $("#board_submit").attr("disabled", false);
                        custom_noty('error', 'Something went wrong at server');
                    });

            },
        });


        var form_id = '#claim_local_travel_expense_form';
        $.validator.addClassRules({
            // maxlength_name: {
            //     maxlength: 191,
            //     required: true,
            // },
            // num_amount: {
            //     maxlength: 12,
            //     number: true,
            //     required: true,
            // },
            // boarding_expense: {
            //     maxlength: 255,
            //     required: true,
            // }
            // attachments: {
            //     // extension: "xlsx,xls",
            // }
        });

        var v = jQuery(form_id).validate({
            // invalidHandler: function(event, validator) {
            //     $noty = new Noty({
            //         type: 'error',
            //         layout: 'topRight',
            //         text: 'Kindly check in each tab to fix errors',
            //         animation: {
            //             speed: 500 // unavailable - no need
            //         },
            //     }).show();
            //     setTimeout(function() {
            //         $noty.close();
            //     }, 1000);
            // },
            ignore: "",
            rules: {},
            errorElement: "div", // default is 'label'
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent())
            },
            submitHandler: function(form) {
                //console.log(self.item);
                let formData = new FormData($(form_id)[0]);
                $('#local_travel_submit').html('loading');
                $("#local_travel_submit").attr("disabled", true);
                $.ajax({
                        url: eyatra_trip_claim_save_url,
                        method: "POST",
                        data: formData,
                        processData: false,
                        contentType: false,
                    })
                    .done(function(res) {
                        //console.log(res.success);
                        if (!res.success) {
                            $('#local_travel_submit').html('Submit');
                            $("#local_travel_submit").attr("disabled", false);
                            var errors = '';
                            for (var i in res.errors) {
                                errors += '<li>' + res.errors[i] + '</li>';
                            }
                            custom_noty('error', errors);
                        } else {
                            $noty = new Noty({
                                type: 'success',
                                layout: 'topRight',
                                text: 'Claim Requested successfully!!',
                                animation: {
                                    speed: 500 // unavailable - no need
                                },
                            }).show();
                            $('#trip-claim-modal-justify-one').modal('hide');
                            setTimeout(function() {
                                $noty.close();
                                $location.path('/eyatra/trip/claim/list')
                                $scope.$apply()
                            }, 500);
                        }
                    })
                    .fail(function(xhr) {
                        $('#local_travel_submit').html('Submit');
                        $("#local_travel_submit").attr("disabled", false);
                        custom_noty('error', 'Something went wrong at server');
                    });

            },
        });
    }
});
//------------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------------

app.component('eyatraTripClaimView', {
    templateUrl: eyatra_trip_claim_view_template_url,
    controller: function($http, $location, $location, HelperService, $routeParams, $rootScope, $scope) {
        $form_data_url = typeof($routeParams.trip_id) == 'undefined' ? eyatra_trip_claim_view_url + '/' : eyatra_trip_claim_view_url + '/' + $routeParams.trip_id;
        var self = this;
        self.hasPermission = HelperService.hasPermission;
        self.angular_routes = angular_routes;
        self.eyatra_trip_claim_visit_attachment_url = eyatra_trip_claim_visit_attachment_url;
        self.eyatra_trip_claim_lodging_attachment_url = eyatra_trip_claim_lodging_attachment_url;
        self.eyatra_trip_claim_boarding_attachment_url = eyatra_trip_claim_boarding_attachment_url;
        self.eyatra_trip_claim_local_travel_attachment_url = eyatra_trip_claim_local_travel_attachment_url;
        $http.get(
            $form_data_url
        ).then(function(response) {
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
                }, 1000);
                $location.path('/eyatra/trip/claim/list')
                $scope.$apply()
                return;
            }
            self.trip = response.data.trip;
            self.gender = (response.data.trip.employee.gender).toLowerCase();
            self.travel_cities = response.data.travel_cities;
            self.travel_dates = response.data.travel_dates;
            self.transport_total_amount = response.data.transport_total_amount;
            self.lodging_total_amount = response.data.lodging_total_amount;
            self.boardings_total_amount = response.data.boardings_total_amount;
            self.local_travels_total_amount = response.data.local_travels_total_amount;
            self.total_amount = response.data.total_amount;
            if (self.trip.advance_received) {
                if (parseInt(self.total_amount) > parseInt(self.trip.advance_received)) {
                    self.pay_to_employee = (parseInt(self.total_amount) - parseInt(self.trip.advance_received)).toFixed(2);
                    self.pay_to_company = '0.00';
                } else if (parseInt(self.total_amount) < parseInt(self.trip.advance_received)) {
                    self.pay_to_employee = '0.00';
                    self.pay_to_company = (parseInt(self.trip.advance_received) - parseInt(self.total_amount)).toFixed(2);
                } else {
                    self.pay_to_employee = '0.00';
                    self.pay_to_company = '0.00';
                }
            } else {
                self.pay_to_employee = parseInt(self.total_amount).toFixed(2);
                self.pay_to_company = '0.00';
            }
            $rootScope.loading = false;

        });

        //TOOLTIP MOUSEOVER
        $(document).on('mouseover', ".attachment_tooltip", function() {
            var $this = $(this);
            $this.tooltip({
                title: $this.attr('data-title'),
                placement: "top"
            });
            $this.tooltip('show');
        });

        /* Pane Next Button */
        $('.btn-nxt').on("click", function() {
            $('.editDetails-tabs li.active').next().children('a').trigger("click");
        });
        $('.btn-prev').on("click", function() {
            $('.editDetails-tabs li.active').prev().children('a').trigger("click");
        });

    }
});