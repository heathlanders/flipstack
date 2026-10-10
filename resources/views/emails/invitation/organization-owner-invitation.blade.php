<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Your Organization is Ready</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; line-height: 1.6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">

                    <!-- Header with Organization Branding -->
                    <tr>
                        <td style="padding: 32px 40px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                            @if($organization->logo_url)
                                <img src="{{ $organization->logo_url }}" alt="{{ $organization->name }}" style="max-height: 60px; max-width: 200px; margin-bottom: 16px;">
                            @else
                                <div style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #1e293b 0%, #312e81 100%); border-radius: 8px; margin-bottom: 16px;">
                                    <span style="color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.025em;">{{ $organization->name }}</span>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Invitation Badge -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <span style="display: inline-block; padding: 6px 16px; background-color: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 9999px;">
                                            Organization Setup
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <!-- Greeting -->
                            <h1 style="margin: 0 0 16px 0; font-size: 28px; font-weight: 700; color: #111827; text-align: center;">
                                Your Organization is Ready
                            </h1>

                            <p style="margin: 0 0 24px 0; font-size: 16px; color: #4b5563; text-align: center;">
                                You've been selected to set up and manage
                                <strong style="color: #111827;">{{ $organization->name }}</strong> on the platform.
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: #ffffff; font-size: 16px; font-weight: 600; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.5);">
                                            Set Up Your Organization
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiration Warning -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <span style="display: inline-flex; align-items: center; padding: 8px 16px; background-color: #fef3c7; color: #92400e; font-size: 13px; border-radius: 6px;">
                                            <span style="margin-right: 6px;">&#9888;</span>
                                            This invitation expires on <strong style="margin-left: 4px;">{{ $expiresAt }}</strong>
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

                            <!-- What to Expect Section -->
                            <h2 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
                                What to Expect
                            </h2>

                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 40px; vertical-align: top;">
                                                    <div style="width: 28px; height: 28px; background-color: #e0e7ff; border-radius: 6px; text-align: center; line-height: 28px; font-size: 14px; color: #3730a3;">1</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0; font-size: 14px; color: #374151;">
                                                        <strong>Create your account</strong> or sign in with your existing credentials
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 40px; vertical-align: top;">
                                                    <div style="width: 28px; height: 28px; background-color: #e0e7ff; border-radius: 6px; text-align: center; line-height: 28px; font-size: 14px; color: #3730a3;">2</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0; font-size: 14px; color: #374151;">
                                                        <strong>Configure your organization</strong> including name, subdomain, and settings
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 0;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 40px; vertical-align: top;">
                                                    <div style="width: 28px; height: 28px; background-color: #e0e7ff; border-radius: 6px; text-align: center; line-height: 28px; font-size: 14px; color: #3730a3;">3</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0; font-size: 14px; color: #374151;">
                                                        <strong>Invite your team</strong> and assign roles to start collaborating
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

                            <!-- As Organization Owner Section -->
                            <h2 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
                                As Organization Owner
                            </h2>

                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f3ff; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                                            <tr>
                                                <td style="width: 24px; vertical-align: top; padding-top: 2px;">
                                                    <span style="display: inline-block; width: 8px; height: 8px; background-color: #4f46e5; border-radius: 50%;"></span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 14px; color: #374151;">Manage team members and their permissions</span>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                                            <tr>
                                                <td style="width: 24px; vertical-align: top; padding-top: 2px;">
                                                    <span style="display: inline-block; width: 8px; height: 8px; background-color: #4f46e5; border-radius: 50%;"></span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 14px; color: #374151;">Configure organization settings and integrations</span>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                                            <tr>
                                                <td style="width: 24px; vertical-align: top; padding-top: 2px;">
                                                    <span style="display: inline-block; width: 8px; height: 8px; background-color: #4f46e5; border-radius: 50%;"></span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 14px; color: #374151;">Access all inventory, products, and reporting</span>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="width: 24px; vertical-align: top; padding-top: 2px;">
                                                    <span style="display: inline-block; width: 8px; height: 8px; background-color: #4f46e5; border-radius: 50%;"></span>
                                                </td>
                                                <td>
                                                    <span style="font-size: 14px; color: #374151;">Full control over billing and subscription management</span>
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
                                If you did not expect this invitation, you can safely ignore this email.
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
                                Having trouble with the button? Copy and paste this URL into your browser:
                            </p>
                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #6b7280; word-break: break-all;">
                                {{ $acceptUrl }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
