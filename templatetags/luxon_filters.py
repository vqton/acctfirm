from luxon import Moment
from django import template

register = template.Library()


@register.filter
def luxon_format(value, format_string):
    moment = Moment(value)
    return moment.format(format_string)
