<!DOCTYPE HTML>
	<html>
		<head>

			<title>{% block title %}{% endblock %}</title>

{% block meta %}
			<meta charset="utf-8">{% endblock %}


		</head>
		<body>

{% block page %}{% endblock %}

			<footer>
				Powered by <strong>Iceberg</strong>.
			</footer>

		</body>
	</html>