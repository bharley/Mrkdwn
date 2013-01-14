Mrkdwn
====
Mrkdwn is a [Markdown][] parser written in PHP. Markdown is a nice
markup language with an emphasis on readability. It was created by
John Grubber. You can always find out more about it on the [official
website][markdown] or on the [Wikipedia entry][wiki] for it.

Mrkdwn is based heavily on the official Perl script.

[markdown]: http://daringfireball.net/projects/markdown "Markdown"
[wiki]: http://en.wikipedia.org/wiki/Markdown "Markdown - Wikipedia"

Usage
----
Using Mrkdwn is pretty straightforward. Simply include `Mrkdwn.php`,
instantiate it and call the `Mrkdwn::parse()` passing the string you
want to parse.
    include 'Mrkdwn.php';
	$mrkdwn = new Mrkdwn;
	$html = $mrkdwn->parse($markdown);

There's also a similar example in the `demos` folder.

Issues
----
I haven't had time to test the script extensively, but I am aware of
a few issues. I have noticed a few interesting behaviors around lists,
but it seems this behavior is also present in the [dingus][] page.

[dingus]: http://daringfireball.net/projects/markdown/dingus "Markdown Dingus"

