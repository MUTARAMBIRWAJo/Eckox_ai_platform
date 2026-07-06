import { render, screen } from "@testing-library/react";
import { StreamingMessage } from "@/components/chat/streaming-message";

describe("StreamingMessage - Error Response Confidence Score Bug", () => {
  it("should never display confidence score on error messages", () => {
    const errorContent = "Sorry, I encountered an error. Please try again.";
    
    // Error message with orphaned confidence value (this should never happen after the fix)
    const { container } = render(
      <StreamingMessage
        content={errorContent}
        role="assistant"
        isStreaming={false}
        confidence={undefined}
      />
    );

    // Verify error message is displayed
    expect(screen.getByText(errorContent)).toBeInTheDocument();

    // Verify confidence score is NOT rendered
    expect(screen.queryByText(/Confidence:/)).not.toBeInTheDocument();
  });

  it("should display confidence score only on successful assistant responses", () => {
    const successContent = "Here's a real response with analysis.";
    
    const { container } = render(
      <StreamingMessage
        content={successContent}
        role="assistant"
        isStreaming={false}
        confidence={85}
      />
    );

    // Verify success message is displayed
    expect(screen.getByText(successContent)).toBeInTheDocument();

    // Verify confidence score IS rendered for valid responses
    expect(screen.getByText("Confidence: 85%")).toBeInTheDocument();
  });

  it("should not render confidence for user messages regardless of confidence value", () => {
    const { container } = render(
      <StreamingMessage
        content="User question"
        role="user"
        isStreaming={false}
        confidence={95}
      />
    );

    // User messages should never show confidence, even if passed
    expect(screen.queryByText(/Confidence:/)).not.toBeInTheDocument();
  });
});
