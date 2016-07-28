jQuery.entwine('ss.workflow', function ($) {
    $('.futurestatepreview .preview-action').entwine({
        onclick: function () {
            var self = this;
            self.openLink();

            return false;
        },
        /*
         * Adds padding if it is a single digit
         */
        pad: function (number) {
            if (number < 10) {
                return '0' + number;
            }
            return number;
        },
        /*
         * Copied from LeftAndMain in framework
         */
        statusMessage: function (text, type) {
            text = jQuery('<div/>').text(text).html();
            jQuery.noticeAdd({ text: text, type: type, stayTime: 5000, inEffect: { left: '0', opacity: 'show' } });
        },
        /*
         * returns date in the format yyyymmdd
         */
        getISODate: function () {
            var self = this,
                input = self.closest('.futurestatepreview').find(':input.date'),
                inputDate = input.datepicker('getDate'),
                outputDate;

            // no date given, use date NOW
            if (input.val() === '') {
                inputDate = new Date();
                input.datepicker('setDate', inputDate);
            }
            if (inputDate) {
                outputDate = '' + inputDate.getFullYear() + self.pad(inputDate.getMonth() + 1) + self.pad(inputDate.getDate());
            }

            return outputDate;
        },
        /*
         * returns the time in format hhmm
         */
        getISOTime: function () {
            var self = this,
                input = self.closest('.futurestatepreview').find(':input.time'),
                inputTime = input.datepicker('getDate'),
                outputTime;

            // no time given, use time NOW
            if (input.val() === '') {
                inputTime = new Date();
                input.datepicker('setDate', inputTime);
                input.val('');
            }
            if (inputTime) {
                outputTime = '' + self.pad(inputTime.getHours()) + self.pad(inputTime.getMinutes());
            }

            return outputTime;
        },
        /*
         * returns the datetime in ISO8601 format yyyymmddThhmmZ
         */
        getISODateTime: function () {
            var self = this,
                isoDate = self.getISODate(),
                isoTime = self.getISOTime();

            if (isoDate && isoTime) {
                return isoDate + 'T' + isoTime + 'Z';
            }
        },
        /*
         * Opens the link for the desired future state, notifies user if something is wrong
         */
        openLink: function () {
            var self = this,
                futureDateTime = self.getISODateTime(),
                previewUrl = $('.preview').attr('href');

            if (!self.checkIsFutureDatetime()) {
                self.statusMessage('Please enter future date and time', 'bad');
                return;
            }

            if (futureDateTime) {
                if (!previewUrl) {
                    self.statusMessage('Unable to determine to the URL to view', 'bad');
                    return;
                }
                window.open(previewUrl + '&ft=' + futureDateTime);
            } else {
                self.statusMessage('Please enter a proper date and time', 'bad');
            }
        },
        /*
         * Checks if the preview date is in the future
         */
        checkIsFutureDatetime: function () {
            var datetime,
                now = new Date(),

                holder = this.closest('.futurestatepreview'),
                date = holder.find(':input.date').datepicker('getDate'),
                time = holder.find(':input.time').datepicker('getDate');

            datetime = new Date(
                date.getFullYear(),
                date.getMonth(),
                date.getDate(),
                time.getHours(),
                time.getMinutes()
            );

            return datetime > now;
        }
    });

});
