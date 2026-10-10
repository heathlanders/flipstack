<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Welcome to {{ $organization->name }}!</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; line-height: 1.6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">

                    <!-- Header with Organization Branding -->
                    <tr>
                        <td style="padding: 32px 40px; text-align: center; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 12px 12px 0 0;">
                            @if($organization->logo_url)
                                <img src="{{ $organization->logo_url }}" alt="{{ $organization->name }}" style="max-height: 50px; max-width: 180px; margin-bottom: 16px;">
                            @endif
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                Welcome aboard!
                            </h1>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Personalized Greeting -->
                            <p style="margin: 0 0 24px 0; font-size: 18px; color: #111827;">
                                Hi <strong>{{ $user->name }}</strong>,
                            </p>

                            <p style="margin: 0 0 24px 0; font-size: 16px; color: #4b5563;">
                                You've successfully joined <strong style="color: #111827;">{{ $organization->name }}</strong>!
                                We're excited to have you on the team.
                            </p>

                            <!-- Role Information -->
                            @if(count($roles) > 0)
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 24px; background-color: #f9fafb; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #374151;">
                                            Your role{{ count($roles) > 1 ? 's' : '' }}:
                                        </p>
                                        @foreach($roles as $role)
                                        <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                                            <tr>
                                                <td style="width: 24px; vertical-align: top; padding-top: 2px;">
                                                    <span style="display: inline-block; width: 8px; height: 8px; background-color: #3b82f6; border-radius: 50%;"></span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 15px; font-weight: 500; color: #1f2937;">{{ $role['name'] }}</span>
                                                    @if(!empty($role['description']))
                                                    <br><span style="font-size: 13px; color: #6b7280;">{{ $role['description'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $onboardingUrl }}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.5);">
                                            Get Started
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

                            <!-- Quick Start Links -->
                            @if(count($quickStartLinks) > 0)
                            <h2 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: #111827;">
                                Quick Start
                            </h2>

                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                                @foreach($quickStartLinks as $link)
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 48px; vertical-align: top;">
                                                    <div style="width: 36px; height: 36px; background-color: #dbeafe; border-radius: 8px; text-align: center; line-height: 36px;">
                                                        @if($link['icon'] === 'rocket')
                                                        <span style="font-size: 16px;">&#128640;</span>
                                                        @elseif($link['icon'] === 'cog')
                                                        <span style="font-size: 16px;">&#9881;</span>
                                                        @elseif($link['icon'] === 'users')
                                                        <span style="font-size: 16px;">&#128101;</span>
                                                        @elseif($link['icon'] === 'shopping-bag')
                                                        <span style="font-size: 16px;">&#128722;</span>
                                                        @elseif($link['icon'] === 'clipboard-list')
                                                        <span style="font-size: 16px;">&#128203;</span>
                                                        @elseif($link['icon'] === 'cube')
                                                        <span style="font-size: 16px;">&#128230;</span>
                                                        @else
                                                        <span style="font-size: 16px;">&#9889;</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="{{ $link['url'] }}" style="font-size: 15px; font-weight: 500; color: #1d4ed8; text-decoration: none;">
                                                        {{ $link['title'] }}
                                                    </a>
                                                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">
                                                        {{ $link['description'] }}
                                                    </p>
                                                </td>
                                                <td style="width: 24px; vertical-align: middle; text-align: right;">
                                                    <span style="color: #9ca3af;">&rarr;</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                            @endif

                            <!-- Help Section -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 32px; vertical-align: top;">
                                                    <span style="font-size: 20px;">&#128161;</span>
                                                </td>
                                                <td>
                                                    <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #166534;">
                                                        Need help getting started?
                                                    </p>
                                                    <p style="margin: 0; font-size: 13px; color: #15803d;">
                                                        Our onboarding guide will walk you through setting up your account and getting familiar with all the features.
                                                        If you have any questions, reach out to your organization administrator.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px;">
                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280; text-align: center;">
                                You're receiving this email because you joined {{ $organization->name }}.
                            </p>
                            <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                                &copy; {{ date('Y') }} {{ $organization->name }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Help Text -->
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; margin-top: 24px;">
                    <tr>
                        <td align="center">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                <a href="{{ $dashboardUrl }}" style="color: #6b7280; text-decoration: underline;">Go to Dashboard</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
